<?php

namespace App\Http\Controllers\Artifacts;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{DB, Schema, Log, Auth, Cache};
use Symfony\Component\HttpKernel\Exception\InvalidArgumentException;

class UpdateController extends Controller
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
                    ->where('status', 'active')->value('key');
            });

            if (!$this->actorKey) {
                throw new \Exception('No valid actor key found');
            }
        } catch (\Exception $e) {
            Log::error('UpdateController initialization failed', [
                'error' => $e->getMessage(),
                'user_key' => Auth::user()->key ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }


    /*
        validations and checks begin here.
    */

    private function checkUpdatePermission()
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        return (new ConstantsController())
            ->checkModulePermission($user, $this->app_key, $this->bns_key, $action = 'update');
    }

    public function validateTableAccess(string $table): void
    {
        if (!Schema::hasTable($table)) {
            throw new \InvalidArgumentException("Table '{$table}' does not exist");
        }

        $restrictedTables = ['migrations','cache', 'sessions', 'auth_user_audit_trails'];

        if (in_array($table, $restrictedTables)) {
            throw new \Exception("Access denied to table '{$table}'");
        }
    }

    private function validateOptimisticLock(string $table, string $key, $expectedVersion = null): object
    {
        $columns = Schema::getColumnListing($table);

        if (!in_array('version', $columns) && !in_array('updated_at', $columns)) {
            return DB::table($table)->where('key', $key)->firstOrFail();
        }

        $currentRow = DB::table($table)->where('key', $key)->first();

        if (!$currentRow) {
            throw new \Exception("Record not found in {$table} with key {$key}");
        }

        if ($expectedVersion !== null) {
            if (in_array('version', $columns)) {
                if ($currentRow->version != $expectedVersion) {
                    throw new \Exception("Record has been modified by another user. Expected version: {$expectedVersion}, Current version: {$currentRow->version}");
                }
            } elseif (in_array('updated_at', $columns)) {
                if ($currentRow->updated_at != $expectedVersion) {
                    throw new \Exception("Record has been modified by another user. Please refresh and try again.");
                }
            }
        }

        return $currentRow;
    }

    private function incrementVersion(string $table, string $key, array &$updateData)
    {
        $columns = Schema::getColumnListing($table);

        if (in_array('version', $columns)) {
            $updateData['version'] = DB::raw('version + 1');
            $currentVersion = $this->getCurrentVersion($table, $key);
            return $currentVersion + 1;
        }

        return 0;
    }

    private function getCurrentVersion(string $table, string $key): int
    {
        $columns = Schema::getColumnListing($table);

        if (!in_array('version', $columns)) {
            return 0;
        }

        $version = DB::table($table)
            ->where('key', $key)
            ->value('version');

        return $version ?? 0;
    }



    private function detectChangedFields(object $existingRow, array $newData): array
    {
        $changes = [];
        $existingArray = (array) $existingRow;

        foreach ($newData as $field => $newValue) {
            $oldValue = $existingArray[$field] ?? null;

            if ($this->valuesAreDifferent($oldValue, $newValue)) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return $changes;
    }

    private function valuesAreDifferent($oldValue, $newValue): bool
    {
        if ($oldValue === null && $newValue === null) {
            return false;
        }

        if ($oldValue === null || $newValue === null) {
            return true;
        }

        if (is_array($oldValue) || is_object($oldValue) || is_array($newValue) || is_object($newValue)) {
            return json_encode($oldValue) !== json_encode($newValue);
        }

        if (is_bool($oldValue) || is_bool($newValue)) {
            return (bool)$oldValue !== (bool)$newValue;
        }

        if (is_numeric($oldValue) && is_numeric($newValue)) {
            return (float)$oldValue !== (float)$newValue;
        }

        if ($oldValue instanceof \DateTime || $newValue instanceof \DateTime) {
            return (string)$oldValue !== (string)$newValue;
        }

        return (string)$oldValue !== (string)$newValue;
    }

    private function buildOptimizedUpdateData(array $changes, array $systemFields): array
    {
        if (empty($changes)) {
            return [];
        }

        $updateData = $systemFields;

        foreach ($changes as $field => $change) {
            $updateData[$field] = $change['new'];
        }

        return $updateData;
    }



    private function validateBatchOperations(string $table, array $operations): array
    {
        $validatedOperations = [];
        $errors = [];

        foreach ($operations as $key => $data) {
            try {
                $existingRow = DB::table($table)->where('key', $key)->first();
                if (!$existingRow) {
                    $errors[$key] = "Record not found with key {$key}";
                    continue;
                }

                $cleanData = $this->validateDataForUpdate($table, $data);

                $this->validateUniqueConstraintsForUpdate($table, $cleanData, $key);

                $validatedOperations[$key] = [
                    'existing_row' => $existingRow,
                    'clean_data' => $cleanData,
                    'changed_fields' => $this->detectChangedFields($existingRow, $cleanData)
                ];
            } catch (\Exception $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Batch validation failed: ' . json_encode($errors));
        }

        return $validatedOperations;
    }

    private function validateUniqueConstraintsForUpdate(string $table, array $data, string $currentKey): bool
    {
        try {
            $constraints = DB::select("
                SELECT 
                    tc.constraint_name as constraint_name,
                    kcu.column_name as column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu 
                    ON tc.constraint_name = kcu.constraint_name 
                    AND tc.table_schema = kcu.table_schema
                WHERE tc.table_name = ? 
                    AND tc.constraint_type IN ('UNIQUE', 'PRIMARY KEY')
                    AND tc.constraint_name != 'PRIMARY'
                ORDER BY tc.constraint_name, kcu.ordinal_position
            ", [$table]);

            $indexGroups = [];
            foreach ($constraints as $constraint) {
                $keyName = $constraint->constraint_name;
                if (!isset($indexGroups[$keyName])) {
                    $indexGroups[$keyName] = [];
                }
                $indexGroups[$keyName][] = $constraint->column_name;
            }

            foreach ($indexGroups as $keyName => $fields) {
                $fieldsToCheck = [];
                $whereConditions = [
                    ['bns_key', '=', $this->bns_key],
                    ['acc_key', '=', $this->acc_key],
                    ['key', '!=', $currentKey]
                ];

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $fieldsToCheck[$field] = $data[$field];
                        $whereConditions[] = [$field, '=', $data[$field]];
                    }
                }

                if (count($fieldsToCheck) === count($fields)) {
                    $exists = DB::table($table)->where($whereConditions)->exists();

                    if ($exists) {
                        $fieldList = implode(', ', array_keys($fieldsToCheck));
                        $valueList = implode(', ', array_values($fieldsToCheck));
                        throw new \InvalidArgumentException("Another record with {$fieldList} '{$valueList}' already exists in {$table}");
                    }
                }
            }

            return true;
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Error validating unique constraints for update", [
                'table' => $table,
                'key' => $currentKey,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Unable to validate unique constraints");
        }
    }


    private function validateDataForUpdate(string $table, array $data): array
    {
        try {
            $columns = Schema::getColumnListing($table);
            $cleanData = [];

            foreach ($data as $field => $value) {
                if (!in_array($field, $columns)) {
                    Log::warning("Field '{$field}' does not exist in table '{$table}', skipping");
                    continue;
                }

                if (is_string($value)) {
                    $cleanedValue = trim($value);
                    $cleanedValue = $cleanedValue === '' ? null : $cleanedValue;
                } else {
                    $cleanedValue = $value;
                }

                $cleanData[$field] = $cleanedValue;
            }

            return $cleanData;
        } catch (\Exception $e) {
            Log::error("Data validation failed for update", [
                'table' => $table,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \InvalidArgumentException("Validation failed: " . $e->getMessage());
        }
    }

    /*
        Updates begin here.
    */

    /**
     * Build secure query with multi-tenant filters
     */
    private function buildSecureQuery(string $table, string $key = null)
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

        // Add key filter if provided
        if ($key !== null) {
            $query->where('key', $key);
        }

        return $query;
    }

    public function UpdateSingleRow(string $table, string $key, array $data, $expectedVersion = null): array
    {
        return DB::transaction(function () use ($table, $key, $data, $expectedVersion) {
            $this->checkUpdatePermission();
            $this->validateTableAccess($table);

            $existingRow = $this->validateOptimisticLock($table, $key, $expectedVersion);

            $cleanData = $this->validateDataForUpdate($table, $data);

            $this->validateUniqueConstraintsForUpdate($table, $cleanData, $key);

            $changes = $this->detectChangedFields($existingRow, $cleanData);

            if (empty($changes)) {
                Log::info("No changes detected for record in {$table}", ['key' => $key]);
                return [
                    'updated' => false,
                    'reason' => 'no_changes',
                    'changes' => [],
                    'version' => $existingRow->version ?? $existingRow->updated_at ?? null
                ];
            }

            $columns = Schema::getColumnListing($table);
            $systemFields = ['updated_at' => now()];

            if (in_array('updated_by', $columns)) {
                $systemFields['updated_by'] = $this->actorKey;
            }

            $this->incrementVersion($table, $key, $systemFields); 
            $updateData = $this->buildOptimizedUpdateData($changes, $systemFields); 

            $updated = DB::table($table)->where('key', $key)->update($updateData);

            if ($updated) { 
                Log::info("Record updated in {$table}", [
                    'key' => $key,
                    'changed_fields' => array_keys($changes),
                    'changes_detail' => $changes
                ]);

                (new CreateController)->RecordAuditTrail(
                    'update',
                    "Updated record in {$table} - Fields changed: " . implode(', ', array_keys($changes)),
                    $table,
                    (array)$existingRow,
                    $updateData
                );
            }

            $updatedRow = DB::table($table)->where('key', $key)->first();

            return [
                'updated' => $updated > 0,
                'reason' => 'success',
                'changes' => $changes,
                'version' => $updatedRow->version ?? $updatedRow->updated_at ?? null,
                'affected_fields' => array_keys($changes)
            ];
        });
    }

    public function BulkUpdateRows(string $table, array $updates): array
    {
        return DB::transaction(function () use ($table, $updates) {

            $this->checkUpdatePermission();
            $this->validateTableAccess($table);

            $validatedOperations = $this->validateBatchOperations($table, $updates);

            $results = [];
            $totalChanges = 0;

            foreach ($validatedOperations as $key => $operation) {
                try {
                    $changes = $operation['changed_fields'];

                    if (empty($changes)) {
                        $results[$key] = [
                            'updated' => false,
                            'reason' => 'no_changes',
                            'changes' => []
                        ];
                        continue;
                    }

                    $columns = Schema::getColumnListing($table);
                    $systemFields = ['updated_at' => now()];

                    if (in_array('updated_by', $columns)) {
                        $systemFields['updated_by'] = $this->actorKey;
                    }

                    $this->incrementVersion($table, $key, $systemFields);
                    $updateData = $this->buildOptimizedUpdateData($changes, $systemFields);

                    $updated = DB::table($table)->where('key', $key)->update($updateData);

                    if ($updated) {
                        (new CreateController)->RecordAuditTrail(
                            'update',
                            "Bulk updated record in {$table}",
                            $table,
                            (array)$operation['existing_row'],
                            $updateData
                        );
                        $totalChanges++;
                    }

                    $results[$key] = [
                        'updated' => $updated > 0,
                        'reason' => 'success',
                        'changes' => $changes
                    ];
                } catch (\Exception $e) {
                    Log::error("Bulk update failed for key {$key}", [
                        'table' => $table,
                        'error' => $e->getMessage()
                    ]);

                    $results[$key] = [
                        'updated' => false,
                        'reason' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info("Bulk update completed in {$table}", [
                'total_requested' => count($updates),
                'total_processed' => count($validatedOperations),
                'total_updated' => $totalChanges,
                'success_rate' => ($totalChanges / count($updates)) * 100 . '%'
            ]);

            return [
                'results' => $results,
                'summary' => [
                    'total_requested' => count($updates),
                    'total_validated' => count($validatedOperations),
                    'total_updated' => $totalChanges,
                    'no_changes' => count(array_filter($results, fn($r) => $r['reason'] === 'no_changes')),
                    'errors' => count(array_filter($results, fn($r) => $r['reason'] === 'error'))
                ]
            ];
        });
    }


    public function BulkUpdateRowsWithDifferentData(string $table, array $updates): array
    {
        return DB::transaction(function () use ($table, $updates) {

            $this->checkUpdatePermission();
            $this->validateTableAccess($table);

            $results = [];

            foreach ($updates as $key => $data) {
                try {
                    $results[$key] = $this->UpdateSingleRow($table, $key, $data);
                } catch (\Exception $e) {
                    Log::error("Bulk update failed for key {$key}", [
                        'table' => $table,
                        'error' => $e->getMessage()
                    ]);
                    $results[$key] = false;
                }
            }

            return $results;
        });
    }


 


    public function IncrementField(string $table, string $key, string $field, int $amount = 1): bool
    {
        return DB::transaction(function () use ($table, $key, $field, $amount) {
            $this->checkUpdatePermission();
            $this->validateTableAccess($table);

            $existingRow = DB::table($table)->where('key', $key)->first();
            if (!$existingRow) {
                throw new \Exception("Record not found in {$table} with key {$key}");
            }

            $columns = Schema::getColumnListing($table);
            $updateData = ['updated_at' => now()];

            if (in_array('updated_by', $columns)) {
                $updateData['updated_by'] = $this->actorKey;
            }

            $updated = DB::table($table)
                ->where('key', $key)
                ->increment($field, $amount, $updateData);

            if ($updated) {
                (new CreateController)->RecordAuditTrail(
                    'update',
                    "Incremented {$field} by {$amount} in {$table}",
                    $table,
                    (array)$existingRow,
                    [$field => "incremented by {$amount}"]
                );

                Log::info("Field incremented in {$table}", [
                    'key' => $key,
                    'field' => $field,
                    'amount' => $amount
                ]);
            }

            return $updated > 0;
        });
    }
    


    public function DecrementField(string $table, string $key, string $field, int $amount = 1): bool
    {
        return DB::transaction(function () use ($table, $key, $field, $amount) {
            $this->checkUpdatePermission();
            $this->validateTableAccess($table);

            $existingRow = DB::table($table)->where('key', $key)->first();
            if (!$existingRow) {
                throw new \Exception("Record not found in {$table} with key {$key}");
            }

            $columns = Schema::getColumnListing($table);
            $updateData = ['updated_at' => now()];

            if (in_array('updated_by', $columns)) {
                $updateData['updated_by'] = $this->actorKey;
            }

            $updated = DB::table($table)
                ->where('key', $key)
                ->decrement($field, $amount, $updateData);

            if ($updated) {
                (new CreateController)->RecordAuditTrail(
                    'update',
                    "Decremented {$field} by {$amount} in {$table}",
                    $table,
                    (array)$existingRow,
                    [$field => "decremented by {$amount}"]
                );

                Log::info("Field decremented in {$table}", [
                    'key' => $key,
                    'field' => $field,
                    'amount' => $amount
                ]);
            }

            return $updated > 0;
        });
    }


    public function RestoreRow(string $table, string $key): bool
    {
        return DB::transaction(function () use ($table, $key) {
            $this->checkUpdatePermission();
            $this->validateTableAccess($table);

            $columns = Schema::getColumnListing($table);
            if (!in_array('deleted_at', $columns)) {
                throw new \Exception("Table {$table} does not support soft delete restoration");
            }

            $existingRow = DB::table($table)
                ->where('key', $key)
                ->whereNotNull('deleted_at')
                ->first();

            if (!$existingRow) {
                throw new \Exception("Deleted record not found in {$table} with key {$key}");
            }

            $updateData = [
                'deleted_at' => null,
                'updated_at' => now()
            ];

            if (in_array('updated_by', $columns)) {
                $updateData['updated_by'] = $this->actorKey;
            }

            $updated = DB::table($table)
                ->where('key', $key)->update($updateData);

            if ($updated) {
                (new CreateController)->RecordAuditTrail(
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


    public function ToggleField(string $table, string $key, string $field): bool
    {
        return DB::transaction(function () use ($table, $key, $field) {
            $this->checkUpdatePermission();
            (new ConstantsController())->ValidateTableAccess($table, 'update');

            $existingRow = $this->buildSecureQuery($table, $key)->first();
            if (!$existingRow) {
                throw new \Exception("Record not found in {$table} with key {$key}");
            }

            $currentValue = $existingRow->{$field} ?? 'no';
            $newValue = ($currentValue === 'yes') ? 'no' : 'yes';

            $columns = Schema::getColumnListing($table);
            $updateData = [
                $field => $newValue,
                'updated_at' => now()
            ];

            if (in_array('updated_by', $columns)) {
                $updateData['updated_by'] = $this->actorKey;
            }

            $updated = DB::table($table)->where('key', $key)->update($updateData);

            if ($updated) {
                (new CreateController)->RecordAuditTrail(
                    'update',
                    "Toggled {$field} from {$currentValue} to {$newValue} in {$table}",
                    $table,
                    [$field => $currentValue],
                    [$field => $newValue]
                );

                Log::info('Field toggled successfully', [
                    'table' => $table,
                    'key' => $key,
                    'field' => $field,
                    'from' => $currentValue,
                    'to' => $newValue,
                    'user_key' => $this->actorKey
                ]);

                return true;
            }

            return false;
        });
    }
}
