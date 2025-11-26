<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\WalletHelper;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, DB};
use Illuminate\Validation\Rules;
use Inertia\{Inertia, Response};

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $creator_key = Str::uuid()->toString();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Generate a unique handle based on the user's name
        $user_handle = '@' . Str::slug($request->name);
        $counter = 1;

        while (User::where('handle', $user_handle . $counter)->exists()) {
            $counter++;
        }
        $user_handle .= $counter;

        $userData = [
            'key' => Str::uuid(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'handle' => $user_handle,
            'created_by' => $creator_key,
            'updated_by' => $creator_key,
        ];


        $id = DB::table('users')->insertGetId($userData);
        $user = User::whereId($id)->first();

        $bank = DB::table('wlt_banks')
            ->select('key')->where('is_active', true)->first();

        $accountNumber = rand(6001, 6399) . str_pad(rand(100000, 999999), 8, '0', STR_PAD_LEFT) . rand(10, 99);

        $accountData = [
            'user_key' => $user->key,
            'bank_key' => $bank->key,
            'account_name' => $user->name,
            'account_number' => $accountNumber,
            'account_type' => 'initial', // Use 'initial' to match schema constraints
            'currency' => 'AED',
            'balance' => 0.00,
            'is_active' => true,
            'is_default' => false,
            'version' => 1,
            'created_by' => $user->key,
            'updated_by' => $user->key,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_by' => null,
            'deleted_at' => null
        ];

        // Direct database insertion with UUID generation
        $inserted = DB::table('wlt_accounts')->insert(array_merge($accountData, [
            'key' => DB::raw('uuid_generate_v4()')
        ]));

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
