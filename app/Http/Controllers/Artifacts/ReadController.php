<?php

namespace App\Http\Controllers\Artifacts;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{DB, Schema, Log, Auth, Cache};

class ReadController extends Controller
{
    public $bns_key;
    public $acc_key;
    public $app_key;
    public $actorKey;

    public function __construct()
    {
        try {
            $constants = (new ConstantsController())->getAppConstants();

            $this->acc_key = $constants['acc_key'] ?? null;
            $this->bns_key = $constants['bns_key'] ?? null;
            $this->app_key = $constants['app_key'] ?? null;

            if (!$this->acc_key) {
                Log::warning('Account key not found in session', [
                    'user_id' => Auth::id(),
                    'request_url' => request()->url()
                ]);
                throw new \InvalidArgumentException('Account not found in session');
            }

            if (!$this->bns_key) {
                Log::warning('Business key not found in session', [
                    'user_id' => Auth::id(),
                    'request_url' => request()->url()
                ]);
                throw new \InvalidArgumentException('Business not found in session');
            }

            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $this->actorKey = $user->key ?? Cache::remember('root_user_key', 3600, function () {
                return DB::table('users')
                    ->where('is_root', 'yes')
                    ->where('status', 'active')
                    ->value('key');
            });

            if (!$this->actorKey) {
                throw new \Exception('No valid actor key found');
            }
        } catch (\Exception $e) {
            Log::error('ReadController initialization failed', [
                'error' => $e->getMessage(),
                'user_key' => Auth::user()->key ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check read permissions
     */
    private function checkReadPermission()
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        return (new ConstantsController())
            ->checkModulePermission($user, $this->app_key, $this->bns_key, $action = 'read');
    }



    /**
     * Build secure query with multi-tenant filters only if columns exist
     */
    private function buildSecureQuery(string $table)
    {
        $columns = Schema::getColumnListing($table);
        $query = DB::table($table);

        // Only add multi-tenant filters if columns exist
        if (in_array('bns_key', $columns)) {
            $query->where('bns_key', $this->bns_key);
        }

        if (in_array('acc_key', $columns)) {
            $query->where('acc_key', $this->acc_key);
        }

        return $query;
    }

    /**
     * Add soft delete filter if table supports it
     */
    private function useSoftRemovalFilter($query, string $table, bool $includeSoftDeleted = false)
    {
        $columns = Schema::getColumnListing($table);

        if (in_array('deleted_at', $columns) && !$includeSoftDeleted) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }



    /**
     * Check if a record exists
     */
    public function RecordExists(string $table, string $key): bool
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $query = $this->buildSecureQuery($table)->where('key', $key);
        $query = $this->useSoftRemovalFilter($query, $table);

        return $query->exists();
    }

    /**
     * Get a single record by key or multiple fields
     */
    public function GetSingleRow(string $table, $keyOrFilters, $value = null): ?object
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $query = $this->buildSecureQuery($table);

        // Handle different parameter formats
        if (is_array($keyOrFilters)) {
            // Multiple filters: ['key' => 'value', 'status' => 'active']
            foreach ($keyOrFilters as $field => $fieldValue) {
                $query->where($field, $fieldValue);
            }
            $filters = $keyOrFilters;
        } elseif ($value !== null) {
            // Single field-value pair: ('status', 'active')
            $query->where($keyOrFilters, $value);
            $filters = [$keyOrFilters => $value];
        } else {
            // Legacy format: assume it's a key field
            $query->where('key', $keyOrFilters);
            $filters = ['key' => $keyOrFilters];
        }

        $query = $this->useSoftRemovalFilter($query, $table);

        $record = $query->first();

        if ($record) {
            (new CreateController)->RecordAuditTrail(
                'read',
                "Read record from {$table}",
                $table,
                null,
                ['filters' => $filters]
            );
        }

        return $record;
    }

    /**
     * Get paginated records with filters
     */
    public function GetPaginatedRows(string $table, array $filters = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $query = $this->buildSecureQuery($table);

        // Apply filters
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, 'LIKE', "%{$value}%");
            }
        }

        // Exclude soft deleted records if applicable
        $query = $this->useSoftRemovalFilter($query, $table);

        // Default ordering if created_at exists
        $columns = Schema::getColumnListing($table);
        if (in_array('created_at', $columns)) {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $results = $query->paginate($perPage);

        (new CreateController)->RecordAuditTrail(
            'read',
            "Retrieved paginated records from {$table}",
            $table,
            null,
            ['filters' => $filters, 'per_page' => $perPage, 'total' => $results->total()]
        );

        return $results;
    }

    /**
     * Search records with multiple conditions
     */
    public function SearchRows(string $table, array $searchCriteria, int $limit = 100): \Illuminate\Support\Collection
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $query = $this->buildSecureQuery($table);

        foreach ($searchCriteria as $field => $criteria) {
            if (is_array($criteria)) {
                $operator = $criteria['operator'] ?? '=';
                $value = $criteria['value'] ?? null;

                if ($value !== null) {
                    $query->where($field, $operator, $value);
                }
            } else {
                $query->where($field, '=', $criteria);
            }
        }

        // Exclude soft deleted records
        $query = $this->useSoftRemovalFilter($query, $table);

        $results = $query->limit($limit)->get();

        (new CreateController)->RecordAuditTrail(
            'search',
            "Searched records in {$table}",
            $table,
            null,
            ['search_criteria' => $searchCriteria, 'limit' => $limit, 'count' => $results->count()]
        );

        return $results;
    }

    /**
     * Get table statistics
     */
    public function GetTableStats(string $table): array
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $columns = Schema::getColumnListing($table);
        $baseQuery = $this->buildSecureQuery($table);

        $stats = [
            'total_records' => clone $baseQuery->count()
        ];

        // Add time-based stats if columns exist
        if (in_array('created_at', $columns)) {
            $stats['created_today'] = $this->buildSecureQuery($table)
                ->whereDate('created_at', today())
                ->count();
        }

        if (in_array('updated_at', $columns)) {
            $stats['updated_today'] = $this->buildSecureQuery($table)
                ->whereDate('updated_at', today())
                ->count();
        }

        // Add soft delete stats if applicable
        if (in_array('deleted_at', $columns)) {
            $stats['active_records'] = $this->buildSecureQuery($table)
                ->whereNull('deleted_at')
                ->count();

            $stats['deleted_records'] = $this->buildSecureQuery($table)
                ->whereNotNull('deleted_at')
                ->count();
        }

        (new CreateController)->RecordAuditTrail(
            'read',
            "Retrieved table statistics for {$table}",
            $table,
            null,
            $stats
        );

        return $stats;
    }

    /**
     * Count records with conditions
     */
    public function CountRows(string $table, array $conditions = []): int
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $query = $this->buildSecureQuery($table);

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        // Exclude soft deleted records
        $query = $this->useSoftRemovalFilter($query, $table);

        $count = $query->count();

        (new CreateController)->RecordAuditTrail(
            'read',
            "Counted records in {$table}",
            $table,
            null,
            ['conditions' => $conditions, 'count' => $count]
        );

        return $count;
    }

    /**
     * Get all records (with optional limit) - Use with caution for large datasets
     * @ deprecated Use GetPaginatedRows() or GetRowsInChunks() for better performance
     */
    public function GetAllRows(string $table, int $limit = 1000): \Illuminate\Support\Collection
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        // Safety check for large datasets
        $totalCount = $this->CountRows($table);
        if ($totalCount > $limit) {
            Log::warning("GetAllRows called on large dataset", [
                'table' => $table,
                'total_records' => $totalCount,
                'requested_limit' => $limit
            ]);
        }

        $query = $this->buildSecureQuery($table);
        $query = $this->useSoftRemovalFilter($query, $table);

        $results = $query->limit($limit)->get();

        (new CreateController)->RecordAuditTrail(
            'read',
            "Retrieved records from {$table} (limited)",
            $table,
            null,
            ['limit' => $limit, 'count' => $results->count(), 'total_available' => $totalCount]
        );

        return $results;
    }

    /**
     * Get records in chunks for processing large datasets efficiently
     */
    public function GetRowsInChunks(string $table, int $chunkSize = 100, ?callable $callback = null): int
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $query = $this->buildSecureQuery($table);
        $query = $this->useSoftRemovalFilter($query, $table);

        $processedCount = 0;

        $query->chunk($chunkSize, function ($records) use (&$processedCount, $callback) {
            $processedCount += $records->count();

            if ($callback && is_callable($callback)) {
                $callback($records);
            }
        });

        (new CreateController)->RecordAuditTrail(
            'read',
            "Processed records from {$table} in chunks",
            $table,
            null,
            ['chunk_size' => $chunkSize, 'total_processed' => $processedCount]
        );

        return $processedCount;
    }

    /**
     * Get all records with automatic pagination (safer alternative to GetAllRows)
     */
    public function GetAllRowsPaginated(string $table, int $perPage = 100): array
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $totalCount = $this->CountRows($table);
        $allRecords = collect();
        $currentPage = 1;
        $totalPages = ceil($totalCount / $perPage);

        while ($currentPage <= $totalPages) {
            $query = $this->buildSecureQuery($table);
            $query = $this->useSoftRemovalFilter($query, $table);

            $pageRecords = $query->offset(($currentPage - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $allRecords = $allRecords->merge($pageRecords);
            $currentPage++;

            // Safety break if no more records
            if ($pageRecords->isEmpty()) {
                break;
            }
        }

        (new CreateController)->RecordAuditTrail(
            'read',
            "Retrieved all records from {$table} via pagination",
            $table,
            null,
            ['per_page' => $perPage, 'total_pages' => $totalPages, 'total_records' => $allRecords->count()]
        );

        return [
            'records' => $allRecords,
            'total_count' => $totalCount,
            'pages_processed' => $totalPages,
            'per_page' => $perPage
        ];
    }

    /**
     * Stream records for very large datasets (memory efficient)
     */
    public function StreamRows(string $table, callable $callback, int $chunkSize = 1000): int
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Callback must be callable');
        }

        $query = $this->buildSecureQuery($table);
        $query = $this->useSoftRemovalFilter($query, $table);

        $processedCount = 0;

        $query->chunk($chunkSize, function ($records) use (&$processedCount, $callback) {
            $processedCount += $records->count();

            foreach ($records as $record) {
                $callback($record);
            }
        });

        (new CreateController)->RecordAuditTrail(
            'read',
            "Streamed records from {$table}",
            $table,
            null,
            ['chunk_size' => $chunkSize, 'total_streamed' => $processedCount]
        );

        return $processedCount;
    }

    /**
     * Get records by specific field value
     */
    public function GetRowsByField(string $table, string $field, $value): \Illuminate\Support\Collection
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $query = $this->buildSecureQuery($table)->where($field, $value);

        // Exclude soft deleted records if applicable
        $query = $this->useSoftRemovalFilter($query, $table);

        $results = $query->get();

        (new CreateController)->RecordAuditTrail(
            'read',
            "Retrieved records from {$table} by {$field}",
            $table,
            null,
            ['field' => $field, 'value' => $value, 'count' => $results->count()]
        );

        return $results;
    }

    /**
     * Get soft deleted records (admin function)
     */
    public function GetSoftDeletedRows(string $table, int $limit = 100): \Illuminate\Support\Collection
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $columns = Schema::getColumnListing($table);
        if (!in_array('deleted_at', $columns)) {
            throw new \Exception("Table {$table} does not support soft delete");
        }

        $query = $this->buildSecureQuery($table)->whereNotNull('deleted_at');
        $results = $query->limit($limit)->get();

        (new CreateController)->RecordAuditTrail(
            'read',
            "Retrieved soft deleted records from {$table}",
            $table,
            null,
            ['limit' => $limit, 'count' => $results->count()]
        );

        return $results;
    }

    /**
     * Advanced search with multiple operators and conditions
     */
    public function AdvancedSearch(string $table, array $conditions, array $options = []): \Illuminate\Support\Collection
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        $limit = $options['limit'] ?? 100;
        $orderBy = $options['order_by'] ?? 'id';
        $orderDirection = $options['order_direction'] ?? 'desc';
        $includeSoftDeleted = $options['include_soft_deleted'] ?? false;

        $query = $this->buildSecureQuery($table);

        // Apply advanced conditions
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            $logic = $condition['logic'] ?? 'AND'; // AND or OR

            if ($field && $value !== null) {
                if (strtoupper($logic) === 'OR') {
                    $query->orWhere($field, $operator, $value);
                } else {
                    $query->where($field, $operator, $value);
                }
            }
        }

        // Handle soft deletes
        $query = $this->useSoftRemovalFilter($query, $table, $includeSoftDeleted);

        // Apply ordering
        $columns = Schema::getColumnListing($table);
        if (in_array($orderBy, $columns)) {
            $query->orderBy($orderBy, $orderDirection);
        }

        $results = $query->limit($limit)->get();

        (new CreateController)->RecordAuditTrail(
            'search',
            "Advanced search in {$table}",
            $table,
            null,
            ['conditions' => $conditions, 'options' => $options, 'count' => $results->count()]
        );

        return $results;
    }

    /**
     * Get records where field value is in an array (whereIn functionality)
     */
    public function GetRowsWhereIn(string $table, string $field, array $values, int $limit = 1000): \Illuminate\Support\Collection
    {
        $this->checkReadPermission();
        (new ConstantsController())->ValidateTableAccess($table, 'read');

        if (empty($values)) {
            return collect();
        }

        $query = $this->buildSecureQuery($table)->whereIn($field, $values);

        // Exclude soft deleted records if applicable
        $query = $this->useSoftRemovalFilter($query, $table);

        $results = $query->limit($limit)->get();

        (new CreateController)->RecordAuditTrail(
            'read',
            "Retrieved records from {$table} where {$field} in array",
            $table,
            null,
            ['field' => $field, 'values_count' => count($values), 'results_count' => $results->count()]
        );

        return $results;
    }
}
