<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Auth};
use App\Http\Controllers\Artifacts\ReadController;

class AuditController extends Controller
{
    private $readController;

    public function __construct()
    {
        $this->readController = new ReadController();
    }

    public function index(Request $request)
    {
        try {
            // Validate request parameters - all are optional for initial load
            $validated = $request->validate([
                'search' => 'sometimes|string|max:255',
                'action' => 'sometimes|string|in:create,read,update,delete,login,logout,approve,decline,search,print,prepare,review',
                'date_from' => 'sometimes|date_format:Y-m-d',
                'date_to' => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            // Get filters from validated request (all optional)
            $search = $validated['search'] ?? null;
            $action = $validated['action'] ?? null;
            $dateFrom = $validated['date_from'] ?? null;
            $dateTo = $validated['date_to'] ?? null;
            $perPage = $validated['per_page'] ?? 15;

            // Build search criteria for ReadController - start empty for initial load
            $searchCriteria = [];
            
            // Only add criteria if filters are provided
            if ($action) {
                $searchCriteria['action'] = $action;
            }

            if ($dateFrom) {
                $searchCriteria['action_time'] = [
                    'operator' => '>=',
                    'value' => $dateFrom . ' 00:00:00'
                ];
            }

            if ($dateTo) {
                if (isset($searchCriteria['action_time'])) {
                    // If we already have a date_from, create a range
                    $searchCriteria['action_time'] = [
                        'operator' => 'BETWEEN',
                        'value' => [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
                    ];
                } else {
                    $searchCriteria['action_time'] = [
                        'operator' => '<=',
                        'value' => $dateTo . ' 23:59:59'
                    ];
                }
            }

            // Load all audit trails or filtered ones
            if ($search) {
                // For search, use custom search method
                $auditTrails = $this->searchAuditTrails($search, $searchCriteria, $perPage);
            } else {
                // Load all audit trails with optional filters
                $auditTrails = $this->getAllAuditTrails($searchCriteria, $perPage);
            }

            // Get statistics 
            $stats = $this->getAuditStats($dateFrom, $dateTo);

            // Record audit trail for this read operation
            try {
                DB::table('users_audit_trails')->insert([
                    'key' => \Illuminate\Support\Str::uuid()->toString(),
                    'action' => 'read',
                    'description' => 'Viewed audit trails list with filters',
                    'table_name' => 'users_audit_trails',
                    'prev_data' => null,
                    'new_data' => json_encode([
                        'filters' => $validated,
                        'results_count' => is_array($auditTrails) ? count($auditTrails) : ($auditTrails->count() ?? 0)
                    ]),
                    'action_time' => now(),
                    'user_ip' => $request->ip(),
                    'user_os' => $request->header('User-Agent'),
                    'user_browser' => null,
                    'user_device' => null,
                    'version' => 1,
                    'created_by' => Auth::user()->key ?? null,
                    'updated_by' => Auth::user()->key ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to record audit trail', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'auditTrails' => $auditTrails,
                'stats' => $stats
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Invalid audit trail request', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Invalid request parameters',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to fetch audit trails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'Failed to fetch audit trails',
                'message' => 'An error occurred while retrieving audit data'
            ], 500);
        }
    }

    private function getAllAuditTrails($searchCriteria = [], $perPage = 15)
    {
        try {
            // Use direct DB query with JOIN for better performance on initial load
            $query = DB::table('users_audit_trails as uat')
                ->leftJoin('users', 'users.key', '=', 'uat.created_by')
                ->select([
                    'uat.id',
                    'uat.key',
                    'uat.action',
                    'uat.description',
                    'uat.table_name',
                    'uat.prev_data',
                    'uat.new_data',
                    'uat.action_time',
                    'uat.user_ip',
                    'uat.user_os',
                    'uat.user_browser',
                    'uat.user_device',
                    'uat.created_by',
                    'uat.created_at',
                    'users.name as user_name',
                    'users.email as user_email'
                ])
                ->orderBy('uat.action_time', 'desc');

            // Apply filters if provided
            if (isset($searchCriteria['action'])) {
                $query->where('uat.action', $searchCriteria['action']);
            }

            if (isset($searchCriteria['action_time'])) {
                $timeFilter = $searchCriteria['action_time'];
                if ($timeFilter['operator'] === 'BETWEEN') {
                    $query->whereBetween('uat.action_time', $timeFilter['value']);
                } elseif ($timeFilter['operator'] === '>=') {
                    $query->where('uat.action_time', '>=', $timeFilter['value']);
                } elseif ($timeFilter['operator'] === '<=') {
                    $query->where('uat.action_time', '<=', $timeFilter['value']);
                }
            }

            // Return paginated results
            return $query->paginate($perPage);

        } catch (\Exception $e) {
            Log::error('Failed to get all audit trails', [
                'criteria' => $searchCriteria,
                'per_page' => $perPage,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function searchAuditTrails($search, $additionalCriteria = [], $perPage = 15)
    {
        try {
            // Use ReadController's secure search with complex criteria
            $searchFields = ['description', 'table_name', 'action', 'user_ip'];
            
            // Build complex search criteria
            $criteria = $additionalCriteria;
            
            // Add search term criteria - ReadController will handle secure parameter binding
            $criteria['search_term'] = [
                'operator' => 'SEARCH',
                'fields' => $searchFields,
                'value' => $search
            ];

            // Get search results with user data
            $results = $this->readController->SearchRows(
                'users_audit_trails', 
                $criteria, 
                $perPage,
                [
                    'id', 'key', 'action', 'description', 'table_name', 'prev_data', 'new_data',
                    'action_time', 'user_ip', 'user_os', 'user_browser', 'user_device', 
                    'created_by', 'created_at'
                ]
            );

            return $this->enrichWithUserData($results);

        } catch (\Exception $e) {
            Log::error('Failed to search audit trails', [
                'search_term' => $search,
                'criteria' => $additionalCriteria,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function enrichWithUserData($auditTrails)
    {
        try {
            if (empty($auditTrails) || (!is_array($auditTrails) && !$auditTrails->count())) {
                return $auditTrails;
            }

            // Get unique user IDs from audit trails
            $userKeys = [];
            $items = is_array($auditTrails) ? $auditTrails : $auditTrails->items();
            
            foreach ($items as $trail) {
                if (!empty($trail->created_by)) {
                    $userKeys[] = $trail->created_by;
                }
            }

            $userKeys = array_unique($userKeys);

            if (empty($userKeys)) {
                return $auditTrails;
            }

            // Use ReadController to securely fetch user data
            $users = [];
            foreach ($userKeys as $userKey) {
                try {
                    $user = $this->readController->GetSingleRow('users', $userKey, ['key', 'name', 'email']);
                    if ($user) {
                        $users[$userKey] = $user;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch user data for audit trail', [
                        'user_key' => $userKey,
                        'error' => $e->getMessage()
                    ]);
                    // Continue processing other users
                }
            }

            // Enrich audit trails with user data
            foreach ($items as $trail) {
                if (!empty($trail->created_by) && isset($users[$trail->created_by])) {
                    $trail->user_name = $users[$trail->created_by]->name;
                    $trail->user_email = $users[$trail->created_by]->email;
                } else {
                    $trail->user_name = 'Unknown User';
                    $trail->user_email = null;
                }
            }

            return $auditTrails;

        } catch (\Exception $e) {
            Log::error('Failed to enrich audit trails with user data', [
                'error' => $e->getMessage()
            ]);
            return $auditTrails; // Return original data if enrichment fails
        }
    }

    private function getAuditStats($dateFrom = null, $dateTo = null)
    {
        try {
            // Use direct DB query for statistics instead of ReadController
            $query = DB::table('users_audit_trails');
            
            if ($dateFrom) {
                $query->where('action_time', '>=', $dateFrom . ' 00:00:00');
            }

            if ($dateTo) {
                $query->where('action_time', '<=', $dateTo . ' 23:59:59');
            }

            // Get total actions
            $totalActions = $query->count();

            // Get unique users count
            $uniqueUsers = DB::table('users_audit_trails')
                ->when($dateFrom, function($q) use ($dateFrom) {
                    return $q->where('action_time', '>=', $dateFrom . ' 00:00:00');
                })
                ->when($dateTo, function($q) use ($dateTo) {
                    return $q->where('action_time', '<=', $dateTo . ' 23:59:59');
                })
                ->distinct('created_by')
                ->count('created_by');

            // Get action-specific counts
            $actionCounts = DB::table('users_audit_trails')
                ->when($dateFrom, function($q) use ($dateFrom) {
                    return $q->where('action_time', '>=', $dateFrom . ' 00:00:00');
                })
                ->when($dateTo, function($q) use ($dateTo) {
                    return $q->where('action_time', '<=', $dateTo . ' 23:59:59');
                })
                ->selectRaw("
                    COUNT(CASE WHEN action = 'create' THEN 1 END) as creates,
                    COUNT(CASE WHEN action = 'read' THEN 1 END) as reads,
                    COUNT(CASE WHEN action = 'update' THEN 1 END) as updates,
                    COUNT(CASE WHEN action = 'delete' THEN 1 END) as deletes,
                    COUNT(CASE WHEN action = 'login' THEN 1 END) as logins,
                    COUNT(CASE WHEN action = 'logout' THEN 1 END) as logouts
                ")
                ->first();

            return (object) [
                'total_actions' => $totalActions,
                'unique_users' => $uniqueUsers,
                'creates' => $actionCounts->creates ?? 0,
                'reads' => $actionCounts->reads ?? 0,
                'updates' => $actionCounts->updates ?? 0,
                'deletes' => $actionCounts->deletes ?? 0,
                'logins' => $actionCounts->logins ?? 0,
                'logouts' => $actionCounts->logouts ?? 0
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get audit statistics', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage()
            ]);
            
            // Return default stats on error
            return (object) [
                'total_actions' => 0,
                'unique_users' => 0,
                'creates' => 0,
                'reads' => 0,
                'updates' => 0,
                'deletes' => 0,
                'logins' => 0,
                'logouts' => 0
            ];
        }
    }
}