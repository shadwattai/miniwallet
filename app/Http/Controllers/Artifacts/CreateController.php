<?php

namespace App\Http\Controllers\Artifacts;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{DB, Schema, Log, Auth, Cache};

class CreateController extends Controller
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
            Log::error('CreateController initialization failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }





    private function checkCreatePermission()
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }
 
        if (!$user->status || $user->status !== 'active') {
            throw new \Exception('User account is not active');
        }

        // some more logic for CRUD operations permision

        return true;
    }


    public function ValidateUniqueConstraints(string $table, array $data): bool
    {
        // For single tenant, remove table access validation
        $uniqueFields = $this->getUniqueFields($table);

        foreach ($uniqueFields as $constraint) {
            $fieldsToCheck = [];
            $whereConditions = [];

            foreach ($constraint['fields'] as $field) {
                if (isset($data[$field])) {
                    $fieldsToCheck[$field] = $data[$field];
                    $whereConditions[] = [$field, '=', $data[$field]];
                }
            }

            if (count($fieldsToCheck) === count($constraint['fields'])) {
                $exists = DB::table($table)->where($whereConditions)->exists();

                if ($exists) {
                    $fieldList = implode(', ', array_keys($fieldsToCheck));
                    $valueList = implode(', ', array_values($fieldsToCheck));
                    throw new \InvalidArgumentException("Record with {$fieldList} '{$valueList}' already exists in {$table}");
                }
            }
        }

        Log::info("Unique constraints validation completed for table {$table}", [
            'constraints_checked' => count($uniqueFields),
            'unique_fields' => $uniqueFields
        ]);

        return true;
    }

    /**
     * Get unique fields from database schema
     */
    private function getUniqueFields(string $table): array
    {
        $uniqueConstraints = [];

        // PostgreSQL-compatible query to get unique constraints
        $constraints = DB::select("
            SELECT 
                tc.constraint_name as constraint_name,
                kcu.column_name as column_name,
                tc.constraint_type as constraint_type
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

        // Convert to our constraint format
        foreach ($indexGroups as $keyName => $fields) {
            $uniqueConstraints[] = [
                'name' => $keyName,
                'fields' => $fields
            ];
        }

        return $uniqueConstraints;
    }

    public function RecordAuditTrail($action, $description, $tableName, $prevData = null, $newData = null)
    {
        try { 
            $request = request();

            $allowedActions = [ 
               'create','read','update','delete',
               'prepare','review','approve','decline',
               'search','print','login','logout'
            ];

            if (!in_array($action, $allowedActions)) {
                Log::warning("Attempted to record audit trail with invalid action: {$action}", [
                    'description' => $description,
                    'table' => $tableName
                ]);
                return false;
            }

            // Check if audit table exists
            if (!Schema::hasTable('users_audit_trails')) {
                Log::error('Audit trail table does not exist: users_audit_trails');
                return false;
            }

            $auditData = [
                'key' => Str::uuid()->toString(),
                'action' => $action,
                'description' => $description,
                'table_name' => $tableName,
                'prev_data' => $prevData ? json_encode($prevData) : null,
                'new_data' => $newData ? json_encode($newData) : null,
                'action_time' => now(),
                'user_ip' => $request->ip(),
                'user_os' => $request->header('User-Agent'),
                'user_browser' => $request->header('Sec-Ch-Ua'),
                'user_device' => $request->header('Sec-Ch-Ua-Platform'),
                'version' => 1,
                'created_by' => $this->actorKey,
                'updated_by' => $this->actorKey,
                'created_at' => now(),
                'updated_at' => now()
            ];

            Log::info('Attempting to record audit trail', [
                'action' => $action,
                'table' => $tableName,
                'audit_data_keys' => array_keys($auditData)
            ]);

            $result = DB::table('users_audit_trails')->insert($auditData);
            
            if ($result) {
                Log::info('Audit trail recorded successfully', [
                    'action' => $action,
                    'table' => $tableName
                ]);
            } else {
                Log::error('Failed to insert audit trail record');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Error recording audit trail: " . $e->getMessage(), [
                'action' => $action,
                'description' => $description,
                'table' => $tableName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }


    public function CreateSingleRow($table, $data)
    {
        return DB::transaction(function () use ($table, $data) {
            try {
                $this->checkCreatePermission('create');
                
                $cleanData = $this->validateData($table, $data);
                $this->ValidateUniqueConstraints($table, $cleanData);

                // Get table columns to check which system fields exist
                $columns = Schema::getColumnListing($table);
                
                $default = [
                    'key' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Only add created_by if it exists in the table
                if (in_array('created_by', $columns)) {
                    $default['created_by'] = (string) $this->actorKey;
                }

                $row = array_merge($default, $cleanData);
                Log::info("Data to insert in " . $table, ['row' => $row]);

                $did = DB::table($table)->insertGetId($row);

                if (!$did) {
                    throw new \Exception("Failed to insert record into {$table}");
                }

                $key = DB::table($table)->whereId($did)->pluck('key')->first();

                if (!$key) {
                    throw new \Exception("Failed to retrieve key for inserted record in {$table}");
                }

                $existingRow = DB::table($table)->where('key', $key)->first();

                Log::info("Record inserted in {$table}", ['key' => $key]);

                $action = 'create';
                $prevData = null; // For new records, there's no previous data
                $description = "Created new record in {$table}";

                $this->RecordAuditTrail($action, $description, $table, $prevData, $cleanData);

                return $key;
            } catch (\Exception $e) {
                Log::error("Error in CreateSingleRow: " . $e->getMessage(), [
                    'data' => $data,
                    'table' => $table,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }



    public function CreateUpdateSingleRow($table, $data, $rowKey = null)
    {
        return DB::transaction(function () use ($table, $data, $rowKey) {
            try {
                $this->checkCreatePermission('create');
                $cleanData = $this->validateData($table, $data);

                // Get table columns to check which system fields exist
                $columns = Schema::getColumnListing($table);
                
                $default = [
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Only add created_by if it exists in the table
                if (in_array('created_by', $columns)) {
                    $default['created_by'] = (string) $this->actorKey;
                }

                $data = array_merge($default, $cleanData);

                if ($rowKey) {
                    $existingRow = DB::table($table)->where('key', $rowKey)->first();
                    if (!$existingRow) {
                        $this->ValidateUniqueConstraints($table, $cleanData);
                    }
                } else {
                    $this->ValidateUniqueConstraints($table, $cleanData);
                }

                if ($rowKey) {

                    if ($existingRow) {
                        $rowKey = $existingRow->key;

                        $auditData = ['updated_at' => now()];
                        if (in_array('updated_by', $columns)) $auditData['updated_by'] = $this->actorKey;
                        if (in_array('created_at', $columns)) $auditData['created_at'] = $existingRow->created_at;
                        if (in_array('created_by', $columns)) $auditData['created_by'] = $existingRow->created_by ?? $this->actorKey;

                        $data = array_merge($auditData, $data);
                    } else {
                        $rowKey = Str::uuid()->toString();
                        $auditData = ['created_at' => now()];
                        if (in_array('created_by', $columns)) $auditData['created_by'] = $this->actorKey;
                        $data = array_merge($auditData, $data);
                    }
                } else {
                    $rowKey = Str::uuid()->toString();
                    $auditData = ['created_at' => now()];
                    if (in_array('created_by', $columns)) $auditData['created_by'] = $this->actorKey;
                    $data = array_merge($auditData, $data);
                }

                $row = array_merge(['key' => $rowKey], $data);
                $existingRow = DB::table($table)->where('key', $rowKey)->first();

                DB::table($table)->updateOrInsert(['key' => $rowKey], $row);

                $action = $existingRow ? 'edit' : 'add';
                $prevData = $existingRow ? (array)$existingRow : null;
                $description = $existingRow ? "Updated record in {$table}" : "Created new record in {$table}";

                Log::info("Record inserted or updated in {$table}", ['key' => $rowKey]);
                $this->RecordAuditTrail($action, $description, $table, $prevData, $data);

                return $rowKey;
            } catch (\Exception $e) {
                Log::error("Error in CreateUpdateSingleRow: " . $e->getMessage(), ['table' => $table, 'rowKey' => $rowKey, 'data' => $data]);
                throw $e;
            }
        });
    }


    public function validateData(string $table, array $data): array
    {
        $cleanData = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
 
        $columnDetails = DB::select("
            SELECT 
                column_name as \"Field\",
                data_type as \"Type\",
                CASE WHEN is_nullable = 'YES' THEN 'YES' ELSE 'NO' END as \"Null\",
                CASE WHEN column_default IS NOT NULL THEN 'YES' ELSE 'NO' END as \"Default\",
                column_default as \"DefaultValue\",
                CASE WHEN column_default LIKE 'nextval%' THEN 'auto_increment' ELSE '' END as \"Extra\"
            FROM information_schema.columns 
            WHERE table_name = ? 
            ORDER BY ordinal_position
        ", [$table]);

        $requiredFields = [];
        foreach ($columnDetails as $column) { 
            $fieldName = $column->Field ?? $column->field ?? '';
            $nullValue = $column->Null ?? $column->null ?? 'no';
            $defaultValue = $column->DefaultValue ?? $column->default_value ?? $column->column_default ?? null;
            $extraValue = $column->Extra ?? $column->extra ?? '';
            
            $isNullable = strtolower($nullValue) === 'yes';
            $hasDefault = ($defaultValue !== null) || (strtolower($extraValue) === 'auto_increment');
            $isKey = false;
             
            if (strtolower($fieldName) === 'id' || 
                strpos(strtolower($defaultValue ?? ''), 'nextval') !== false) {
                $isKey = true;
            }

            if (!$isNullable && !$hasDefault && !$isKey && !empty($fieldName)) {
                $requiredFields[] = $fieldName;
            }
        }

        $systemFields = ['id', 'key', 'created_by', 'created_at', 'updated_at', 'updated_by'];
        $requiredFields = array_diff($requiredFields, $systemFields);

        foreach ($requiredFields as $field) {
            if (!isset($cleanData[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing for table {$table}");
            }
        }

        foreach ($cleanData as $key => $value) {
            if (is_string($value)) {
                $cleanData[$key] = trim($value);
            }
        }

        Log::info("Data validation completed for table {$table}", [
            'required_fields' => $requiredFields,
            'provided_fields' => array_keys($cleanData)
        ]);

        return $cleanData;
    }


    public function CreateMultipleRows(string $table, array $dataArray): array
    {
        return DB::transaction(function () use ($table, $dataArray) {
            $keys = [];

            foreach ($dataArray as $data) {
                $keys[] = $this->CreateSingleRow($table, $data);
            }

            Log::info("Multiple records created in {$table}", [
                'count' => count($keys),
                'table' => $table
            ]);

            return $keys;
        });
    }


    public function CreateWithRelations(string $table, array $data, array $relationChecks = []): string
    {
        return DB::transaction(function () use ($table, $data, $relationChecks) {
            foreach ($relationChecks as $field => $relationTable) {
                if (isset($data[$field])) {
                    $relationExists = DB::table($relationTable)->where('key', $data[$field])->exists();

                    if (!$relationExists) {
                        throw new \InvalidArgumentException("Related record not found in {$relationTable} for {$field}");
                    }
                }
            }

            return $this->CreateSingleRow($table, $data);
        });
    }

    public function CreateWithAutoFields(string $table, array $data, array $autoFields = []): string
    {
        return DB::transaction(function () use ($table, $data, $autoFields) {
            foreach ($autoFields as $field => $generator) {
                switch ($generator['type']) {
                    case 'sequence':
                        $reservedRanges = $generator['reserved_ranges'] ?? [];
                        $startFrom = $generator['start_from'] ?? 1;
                        $data[$field] = $this->generateSequenceNumber($table, $generator['prefix'] ?? '', $reservedRanges, $startFrom);
                        break;
                    case 'code':
                        $data[$field] = $this->generateCode($generator['pattern'] ?? 'XXX-000');
                        break;
                    case 'slug':
                        $data[$field] = $this->generateSlug($data[$generator['from']] ?? '');
                        break;
                }
            }

            return $this->CreateSingleRow($table, $data);
        });
    }

    public function CreateWithUniqueValidation(string $table, array $data): string
    {
        return DB::transaction(function () use ($table, $data) {
            $this->ValidateUniqueConstraints($table, $data);

            return $this->CreateSingleRow($table, $data);
        });
    }





    private function generateSequenceNumber(string $table, string $prefix = '', array $reservedRanges = [], int $startFrom = 1): string
    {
        $columns = Schema::getColumnListing($table);
        $query = DB::table($table);
        
        // For single tenant, no need for multi-tenant filtering
        $lastNumber = $query->max(DB::raw('CAST(SUBSTRING_INDEX(code, "-", -1) AS UNSIGNED)')) ?? 0;

        $nextNumber = max($lastNumber + 1, $startFrom);

        while ($this->isNumberInReservedRange($nextNumber, $reservedRanges)) {
            $nextNumber = $this->getNextAvailableNumber($nextNumber, $reservedRanges);
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function isNumberInReservedRange(int $number, array $reservedRanges): bool
    {
        foreach ($reservedRanges as $range) {
            $start = $range['start'] ?? 0;
            $end = $range['end'] ?? 0;

            if ($number >= $start && $number <= $end) {
                return true;
            }
        }

        return false;
    }


    private function getNextAvailableNumber(int $currentNumber, array $reservedRanges): int
    {
        foreach ($reservedRanges as $range) {
            $start = $range['start'] ?? 0;
            $end = $range['end'] ?? 0;

            if ($currentNumber >= $start && $currentNumber <= $end) {
                return $end + 1;
            }
        }

        return $currentNumber + 1;
    }


    private function generateCode(string $pattern): string
    {
        $code = $pattern;
        $code = str_replace('YYYY', date('Y'), $code);
        $code = str_replace('MM', date('m'), $code);
        $code = str_replace('DD', date('d'), $code);
        $code = str_replace('XXX', strtoupper(Str::random(3)), $code);
        $code = str_replace('000', str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT), $code);

        return $code;
    }


    private function generateSlug(string $text): string
    {
        return Str::slug($text);
    }
}
