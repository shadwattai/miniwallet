<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Artifacts\ReadController;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        try {
            $readController = new ReadController();
            
            // Get filters from request
            $search = $request->get('search');
            $action = $request->get('action');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $perPage = $request->get('per_page', 15);
            
            // Build query
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

            // Apply filters
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('description', 'ILIKE', "%{$search}%")
                      ->orWhere('table_name', 'ILIKE', "%{$search}%")
                      ->orWhere('action', 'ILIKE', "%{$search}%")
                      ->orWhere('user_ip', 'ILIKE', "%{$search}%");
                });
            }

            if ($action) {
                $query->where('action', $action);
            }

            if ($dateFrom) {
                $query->where('action_time', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->where('action_time', '<=', $dateTo . ' 23:59:59');
            }

            // Get paginated results
            $auditTrails = $query->paginate($perPage);

            // Get statistics
            $stats = $this->getAuditStats($dateFrom, $dateTo);

            return response()->json([
                'auditTrails' => $auditTrails,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch audit trails',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getAuditStats($dateFrom = null, $dateTo = null)
    {
        $query = DB::table('users_audit_trails');

        if ($dateFrom) {
            $query->where('action_time', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('action_time', '<=', $dateTo . ' 23:59:59');
        }

        $stats = $query->selectRaw("
            COUNT(*) as total_actions,
            COUNT(DISTINCT created_by) as unique_users,
            COUNT(CASE WHEN action = 'create' THEN 1 END) as creates,
            COUNT(CASE WHEN action = 'read' THEN 1 END) as reads,
            COUNT(CASE WHEN action = 'update' THEN 1 END) as updates,
            COUNT(CASE WHEN action = 'delete' THEN 1 END) as deletes,
            COUNT(CASE WHEN action = 'login' THEN 1 END) as logins,
            COUNT(CASE WHEN action = 'logout' THEN 1 END) as logouts
        ")->first();

        return $stats;
    }

    public function export(Request $request)
    {
        // Implementation for exporting audit trails to CSV/Excel
        // This can be implemented based on your requirements
        return response()->json(['message' => 'Export functionality to be implemented']);
    }
}