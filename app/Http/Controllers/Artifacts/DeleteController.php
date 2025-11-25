<?php

namespace App\Http\Controllers\Artifacts;
 
use Illuminate\Support\Facades\{DB, Schema, Log, Auth, Cache};
use App\Http\Controllers\Controller;

class DeleteController extends Controller
{
    public $actorKey;

    public function __construct()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $this->actorKey = $user->key ?? $user->id;

            if (!$this->actorKey) {
                throw new \Exception('No valid actor key found');
            }

        } catch (\Exception $e) {
            Log::error('DeleteController initialization failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check delete permissions
     */
    private function checkDeletePermission()
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        // For single tenant, simply check if user is authenticated and active
        if (!$user->status || $user->status !== 'active') {
            throw new \Exception('User account is not active');
        }

        return true;
    }

    /**
     * Build secure query for single tenant
     */
    private function buildSecureQuery(string $table, ?string $key)
    {
        $query = DB::table($table);

        // Add key filter if provided
        if ($key) {
            $query->where('key', $key);
        }

        // For single tenant, no additional filtering needed

        return $query;
    }

    /**
     * Check if table exists and user has permission
     */
    public function validateTableAccess(string $table): void
    {
        if (!Schema::hasTable($table)) {
            throw new \InvalidArgumentException("Table '{$table}' does not exist");
        }

        $restrictedTables = ['migrations','cache', 'sessions', 'users', 'users_audit_trails'];

        if (in_array($table, $restrictedTables)) {
            throw new \Exception("Access denied to table '{$table}'");
        }
    }

    /**
     * Soft delete a record (if table has deleted_at column)
     */
    public function SoftDeleteRow(string $table, string $key): bool
    {
        return DB::transaction(function () use ($table, $key) {
            $this->checkDeletePermission();
            $this->validateTableAccess($table);

            $existingRow = $this->buildSecureQuery($table, $key)->first();
            if (!$existingRow) {
                throw new \Exception("Record not found in {$table} with key {$key}");
            }

            $columns = Schema::getColumnListing($table);
            
            if (in_array('deleted_at', $columns)) {
                // Soft delete
                $updateData = ['deleted_at' => now()];
                
                if (in_array('updated_at', $columns)) {
                    $updateData['updated_at'] = now();
                }

                if (in_array('updated_by', $columns)) {
                    $updateData['updated_by'] = $this->actorKey;
                }

                $updated = $this->buildSecureQuery($table, $key)->update($updateData);

                if ($updated) {
                    (new CreateController())->RecordAuditTrail(
                        'delete', 
                        "Soft deleted record from {$table}",
                        $table,
                        (array)$existingRow,
                        $updateData
                    );

                    Log::info("Record soft deleted in {$table}", ['key' => $key]);
                }

                return $updated > 0;
            } else {
                throw new \Exception("Table {$table} does not support soft delete. Use HardDeleteRow instead.");
            }
        });
    }

    /**
     * Hard delete a record (permanent deletion)
     */
    public function HardDeleteRow(string $table, string $key): bool
    {
        return DB::transaction(function () use ($table, $key) {
            $this->checkDeletePermission();
            $this->validateTableAccess($table);

            $existingRow = $this->buildSecureQuery($table, $key)->first();
            if (!$existingRow) {
                throw new \Exception("Record not found in {$table} with key {$key}");
            }

            $deleted = $this->buildSecureQuery($table, $key)->delete();

            if ($deleted) {
                (new CreateController())->RecordAuditTrail(
                    'delete',
                    "Hard deleted record from {$table}",
                    $table,
                    (array)$existingRow,
                    null
                );

                Log::info("Record hard deleted in {$table}", ['key' => $key]);
            }

            return $deleted > 0;
        });
    }

    /**
     * Delete a record (automatically chooses soft or hard delete)
     */
    public function DeleteRow(string $table, string $key): bool
    {
        $columns = Schema::getColumnListing($table);
        
        if (in_array('deleted_at', $columns)) {
            return $this->SoftDeleteRow($table, $key);
        } else {
            return $this->HardDeleteRow($table, $key);
        }
    }

    /**
     * Bulk delete multiple records
     */
    public function BulkDeleteRows(string $table, array $keys): array
    {
        return DB::transaction(function () use ($table, $keys) {
            $results = [];

            foreach ($keys as $key) {
                try {
                    $results[$key] = $this->DeleteRow($table, $key);
                } catch (\Exception $e) {
                    Log::error("Bulk delete failed for key {$key}", [
                        'table' => $table,
                        'error' => $e->getMessage()
                    ]);
                    $results[$key] = false;
                }
            }

            Log::info("Bulk delete completed in {$table}", [
                'total_keys' => count($keys),
                'successful' => count(array_filter($results)),
                'failed' => count($keys) - count(array_filter($results))
            ]);

            return $results;
        });
    }

    /**
     * Permanently delete all soft deleted records (purge)
     */
    public function PurgeSoftDeletedRows(string $table): int
    {
        return DB::transaction(function () use ($table) {
            $this->checkDeletePermission();
            $this->validateTableAccess($table);

            $columns = Schema::getColumnListing($table);
            if (!in_array('deleted_at', $columns)) {
                throw new \Exception("Table {$table} does not support soft delete");
            }

            // Get all soft deleted records for audit trail
            $query = $this->buildSecureQuery($table, null)->whereNotNull('deleted_at');
            $softDeletedRows = $query->get();

            if ($softDeletedRows->isEmpty()) {
                return 0;
            }

            $purged = $this->buildSecureQuery($table, null)->whereNotNull('deleted_at')->delete();

            if ($purged > 0) {
                (new CreateController())->RecordAuditTrail(
                    'delete',
                    "Purged {$purged} soft deleted records from {$table}",
                    $table,
                    $softDeletedRows->toArray(),
                    null
                );

                Log::info("Soft deleted records purged from {$table}", [
                    'count' => $purged
                ]);
            }

            return $purged;
        });
    }

    /**
     * Delete records by field value
     */
    public function DeleteRowsByField(string $table, string $field, $value): int
    {
        return DB::transaction(function () use ($table, $field, $value) {
            $this->checkDeletePermission();
            $this->validateTableAccess($table);

            // Get records to delete for audit trail
            $recordsToDelete = $this->buildSecureQuery($table, null)->where($field, $value)->get();

            if ($recordsToDelete->isEmpty()) {
                return 0;
            }

            $columns = Schema::getColumnListing($table);
            
            if (in_array('deleted_at', $columns)) {
                // Soft delete
                $updateData = ['deleted_at' => now()];
                
                if (in_array('updated_at', $columns)) {
                    $updateData['updated_at'] = now();
                }

                if (in_array('updated_by', $columns)) {
                    $updateData['updated_by'] = $this->actorKey;
                }

                $deleted = $this->buildSecureQuery($table, null)->where($field, $value)->update($updateData);
            } else {
                // Hard delete
                $deleted = $this->buildSecureQuery($table, null)->where($field, $value)->delete();
            }

            if ($deleted > 0) {
                $deleteType = in_array('deleted_at', $columns) ? 'soft deleted' : 'hard deleted';
                
                (new CreateController())->RecordAuditTrail(
                    'delete',
                    "Bulk {$deleteType} {$deleted} records from {$table} where {$field} = {$value}",
                    $table,
                    $recordsToDelete->toArray(),
                    null
                );

                Log::info("Records {$deleteType} from {$table}", [
                    'field' => $field,
                    'value' => $value,
                    'count' => $deleted
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Get count of records that would be deleted
     */
    public function GetDeletableCount(string $table, array $conditions = []): int
    {
        $this->validateTableAccess($table);

        $query = $this->buildSecureQuery($table, null);

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        // Only count non-deleted records
        $columns = Schema::getColumnListing($table);
        if (in_array('deleted_at', $columns)) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }

    /**
     * Get count of soft deleted records
     */
    public function GetSoftDeletedCount(string $table): int
    {
        $this->validateTableAccess($table);

        $columns = Schema::getColumnListing($table);
        if (!in_array('deleted_at', $columns)) {
            return 0;
        }

        return $this->buildSecureQuery($table, null)->whereNotNull('deleted_at')->count();
    }

    /**
     * Restore a soft deleted record
     */
    public function RestoreRow(string $table, string $key): bool
    {
        return DB::transaction(function () use ($table, $key) {
            $this->checkDeletePermission();
            $this->validateTableAccess($table);

            $columns = Schema::getColumnListing($table);
            if (!in_array('deleted_at', $columns)) {
                throw new \Exception("Table {$table} does not support soft delete restoration");
            }

            $existingRow = $this->buildSecureQuery($table, $key)
                ->whereNotNull('deleted_at')
                ->first();

            if (!$existingRow) {
                throw new \Exception("Deleted record not found in {$table} with key {$key}");
            }

            $updateData = ['deleted_at' => null];
            
            if (in_array('updated_at', $columns)) {
                $updateData['updated_at'] = now();
            }

            if (in_array('updated_by', $columns)) {
                $updateData['updated_by'] = $this->actorKey;
            }

            $updated = $this->buildSecureQuery($table, $key)->update($updateData);

            if ($updated) {
                (new CreateController())->RecordAuditTrail(
                    'update',
                    "Restored record in {$table}",
                    $table,
                    (array)$existingRow,
                    $updateData
                );

                Log::info("Record restored in {$table}", ['key' => $key]);
            }

            return $updated > 0;
        });
    }
}

