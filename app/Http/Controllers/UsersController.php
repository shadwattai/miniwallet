<?php

namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Log, Auth};
use App\Http\Controllers\Artifacts\CreateController;
use App\Http\Controllers\Artifacts\ReadController;
use App\Http\Controllers\Artifacts\UpdateController;
use App\Http\Controllers\Artifacts\DeleteController;

class UsersController extends Controller
{ 
    private $readController;
    private $createController;
    private $updateController;
    private $deleteController;

    public function __construct()
    {
        $this->readController = new ReadController();
        $this->createController = new CreateController();
        $this->updateController = new UpdateController();
        $this->deleteController = new DeleteController();
    }

    public function getUsers()
    {
        try {
            $users = $this->readController->GetAllRows('users', 1000);
            return $users;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve users: ' . $e->getMessage());
            return collect();
        }
    }

    // POST /miniwallet/settings/users - Create new user
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
        ]);

        try {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'handle' => '@' . strtolower(str_replace(' ', '.', $validated['name'])),
                'password' => Hash::make('password123'), // Default password
                'status' => 'active',
                'role' => 'user',
            ];

            // Use Artifacts API CreateController with audit trail
            $userKey = $this->createController->CreateSingleRow(
                'users', 
                $userData,
                'User registration: ' . $validated['name'],
                [
                    'registered_by' => Auth::user()->name ?? 'System',
                    'registration_method' => 'admin_panel',
                    'initial_status' => 'active'
                ]
            );

            if ($userKey) {
                return back()->with('success', 'User created successfully');
            } else {
                return back()->withErrors(['error' => 'Failed to create user']);
            }

        } catch (\Exception $e) {
            Log::error('Failed to create user: ' . $e->getMessage(), [
                'data' => $validated,
                'created_by' => Auth::user()->key ?? 'system'
            ]);
            return back()->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }
    }

    

    // PATCH /miniwallet/users/{key} - Update user
    public function update(Request $request, $key)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        try {
            // Get current user data for audit comparison
            $currentUser = $this->readController->GetSingleRow('users', $key);
            if (!$currentUser) {
                return back()->withErrors(['error' => 'User not found']);
            }

            // Use Artifacts API UpdateController with audit trail
            $result = $this->updateController->UpdateSingleRow(
                'users', 
                $key, 
                $validated,
                'User profile updated: ' . $currentUser->name,
                [
                    'updated_by' => Auth::user()->name ?? 'System',
                    'update_method' => 'admin_panel',
                    'fields_updated' => array_keys($validated)
                ]
            );

            if ($result['updated']) {
                return back()->with('success', 'User updated successfully');
            } else {
                return back()->with('info', 'No changes were made to the user');
            }

        } catch (\Exception $e) {
            Log::error('Failed to update user: ' . $e->getMessage(), [
                'key' => $key,
                'data' => $validated,
                'updated_by' => Auth::user()->key ?? 'system'
            ]);
            return back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }
    }

    // DELETE /miniwallet/users/{key} - Delete user
    public function destroy($key)
    {
        try {
            // Get user details first for validation using Artifacts API
            $user = $this->readController->GetSingleRow('users', $key);
            
            if (!$user) {
                return back()->withErrors(['error' => 'User not found']);
            }

            // Prevent deletion of admin users
            if ($user->role === 'admin') {
                return back()->withErrors(['error' => 'Cannot delete admin users']);
            }

            // Use Artifacts API DeleteController with audit trail
            $deleted = $this->deleteController->DeleteRow(
                'users', 
                $key,
                'User account deleted: ' . $user->name,
                [
                    'deleted_by' => Auth::user()->name ?? 'System',
                    'deletion_method' => 'admin_panel',
                    'user_role' => $user->role,
                    'user_status' => $user->status
                ]
            );

            if ($deleted) {
                return back()->with('success', 'User deleted successfully');
            } else {
                return back()->withErrors(['error' => 'Failed to delete user']);
            }

        } catch (\Exception $e) {
            Log::error('Failed to delete user: ' . $e->getMessage(), [
                'key' => $key,
                'deleted_by' => Auth::user()->key ?? 'system'
            ]);
            return back()->withErrors(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    }

    // GET /miniwallet/users/{key} - Get single user
    public function show($key)
    {
        try {
            $user = $this->readController->GetSingleRow('users', $key);
            
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
            
            return response()->json($user);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user: ' . $e->getMessage(), ['key' => $key]);
            return response()->json(['error' => 'Failed to retrieve user'], 500);
        }
    }    // GET /miniwallet/users/search - Search users
    public function search(Request $request)
    {
        try {
            $readController = new ReadController();
            
            $searchCriteria = [];
            
            if ($request->has('status')) {
                $searchCriteria['status'] = $request->get('status');
            }
            
            if ($request->has('role')) {
                $searchCriteria['role'] = $request->get('role');
            }
            
            if ($request->has('email')) {
                $searchCriteria['email'] = [
                    'operator' => 'LIKE',
                    'value' => '%' . $request->get('email') . '%'
                ];
            }

            $users = $readController->SearchRows('users', $searchCriteria, $request->get('limit', 100));
            
            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Failed to search users: ' . $e->getMessage(), [
                'search_params' => $request->all()
            ]);
            return response()->json(['error' => 'Search failed'], 500);
        }
    }

    // PATCH /miniwallet/users/{key}/status - Toggle user status
    public function toggleStatus($key)
    {
        try {
            $updateController = new UpdateController();
            $result = $updateController->ToggleField('users', $key, 'status');
            
            if ($result) {
                return back()->with('success', 'User status updated successfully');
            } else {
                return back()->withErrors(['error' => 'Failed to update user status']);
            }

        } catch (\Exception $e) {
            Log::error('Failed to toggle user status: ' . $e->getMessage(), ['key' => $key]);
            return back()->withErrors(['error' => 'Failed to update user status: ' . $e->getMessage()]);
        }
    }

    // POST /miniwallet/users/bulk - Bulk operations
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'operations' => 'required|array',
            'operations.*.key' => 'required|string',
            'operations.*.data' => 'required|array',
            'operations.*.data.name' => 'sometimes|string|max:255',
            'operations.*.data.status' => 'sometimes|in:active,inactive',
        ]);

        try {
            $updateController = new UpdateController();
            
            $updates = [];
            foreach ($validated['operations'] as $operation) {
                $updates[$operation['key']] = $operation['data'];
            }
            
            $results = $updateController->BulkUpdateRows('users', $updates);
            
            $successCount = count(array_filter($results['results'], fn($r) => $r['updated']));
            $totalCount = count($updates);
            
            return back()->with('success', "Bulk update completed: {$successCount}/{$totalCount} users updated");

        } catch (\Exception $e) {
            Log::error('Failed to bulk update users: ' . $e->getMessage(), [
                'operations' => $validated['operations']
            ]);
            return back()->withErrors(['error' => 'Bulk update failed: ' . $e->getMessage()]);
        }
    }

    // GET /miniwallet/users/stats - Get user statistics
    public function getStats()
    {
        try {
            $readController = new ReadController();
            $stats = $readController->GetTableStats('users');
            
            // Add custom user-specific stats
            $activeUsers = $readController->CountRows('users', ['status' => 'active']);
            $inactiveUsers = $readController->CountRows('users', ['status' => 'inactive']);
            $adminUsers = $readController->CountRows('users', ['role' => 'admin']);
            
            $customStats = [
                'admin_users' => $adminUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'user_users' => $readController->CountRows('users', ['role' => 'user']),
            ];
            
            return response()->json(array_merge($stats, $customStats));
        } catch (\Exception $e) {
            Log::error('Failed to get user statistics: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get statistics'], 500);
        }
    }

    // DELETE /miniwallet/users/bulk - Bulk delete
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'keys' => 'required|array',
            'keys.*' => 'required|string',
        ]);

        try {
            $readController = new ReadController();
            $deleteController = new DeleteController();
            
            // Validate all users exist and aren't admins
            $validKeys = [];
            foreach ($validated['keys'] as $key) {
                $user = $readController->GetSingleRow('users', $key);
                if ($user && $user->role !== 'admin') {
                    $validKeys[] = $key;
                }
            }
            
            if (empty($validKeys)) {
                return back()->withErrors(['error' => 'No valid users selected for deletion']);
            }
            
            $results = $deleteController->BulkDeleteRows('users', $validKeys);
            
            $successCount = count(array_filter($results));
            $totalCount = count($validKeys);
            
            return back()->with('success', "Bulk delete completed: {$successCount}/{$totalCount} users deleted");

        } catch (\Exception $e) {
            Log::error('Failed to bulk delete users: ' . $e->getMessage(), [
                'keys' => $validated['keys']
            ]);
            return back()->withErrors(['error' => 'Bulk delete failed: ' . $e->getMessage()]);
        }
    }

    // POST /miniwallet/users/{key}/restore - Restore soft deleted user
    public function restore($key)
    {
        try {
            $updateController = new UpdateController();
            $result = $updateController->RestoreRow('users', $key);
            
            if ($result) {
                return back()->with('success', 'User restored successfully');
            } else {
                return back()->withErrors(['error' => 'Failed to restore user']);
            }

        } catch (\Exception $e) {
            Log::error('Failed to restore user: ' . $e->getMessage(), ['key' => $key]);
            return back()->withErrors(['error' => 'Failed to restore user: ' . $e->getMessage()]);
        }
    }
}