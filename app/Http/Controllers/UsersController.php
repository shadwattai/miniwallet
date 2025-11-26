<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Log, Auth, DB};
use App\Http\Controllers\Artifacts\CreateController;
use App\Http\Controllers\Artifacts\ReadController;
use App\Http\Controllers\Artifacts\UpdateController;
use App\Http\Controllers\Artifacts\DeleteController;
use GuzzleHttp\Promise\Create;

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
                'password' => Hash::make('123456789'), // Default password
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

            $user = (new ReadController())->GetSingleRow('users', ['key' => $userKey]);

            $this->CreateUserInitialAccount($user);

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
    }

    // GET /miniwallet/users/search - Search users
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


    /**
     * Create initial wallet account for a new user
     * This method is called during registration when user is not yet authenticated
     */
    public static function CreateUserInitialAccount($user)
    {
        try {
            // Avoid any controller dependencies - use only DB facade
            $bank = DB::table('wlt_banks')
                ->select('key')
                ->where('is_active', true)
                ->first();

            if (!$bank) {
                Log::warning('No active banks available for initial account creation');
                return null;
            }

            // Generate unique account number with retry logic
            $maxAttempts = 5;
            $accountNumber = null;

            for ($i = 0; $i < $maxAttempts; $i++) {
                $accountNumber = rand(6001, 6399) . str_pad(rand(100000, 999999), 8, '0', STR_PAD_LEFT) . rand(10, 99);


                // Check if account number already exists
                $exists = DB::table('wlt_accounts')
                    ->where('account_number', $accountNumber)
                    ->exists();

                if (!$exists) {
                    break; // Unique number found
                }

                if ($i === $maxAttempts - 1) {
                    Log::error('Failed to generate unique account number after ' . $maxAttempts . ' attempts');
                    return null;
                }
            }

            // Prepare minimal account data - only required fields
            $accountData = [
                'user_key' => $user->key,
                'bank_key' => $bank->key,
                'account_number' => $accountNumber,
                'account_name' => $user->name . ' - Initial Account',
                'account_type' => 'initial',
                'currency' => 'AED',
                'balance' => 0.00,
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'created_by' => $user->key,
                'updated_by' => $user->key,
                'key' => DB::raw('uuid_generate_v4()'),
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            // Insert with UUID generation
            $inserted = DB::table('wlt_accounts')->insert($accountData);
 
            if ($inserted) {
                Log::info('Initial wallet account created successfully', [
                    'user_key' => $user->key,
                    'user_name' => $user->name,
                    'account_number' => $accountNumber
                ]);
                return $accountNumber;
            } else {
                Log::error('Failed to insert initial account record');
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Exception creating initial account for user', [
                'user_key' => $user->key ?? 'unknown',
                'user_name' => $user->name ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }
}
