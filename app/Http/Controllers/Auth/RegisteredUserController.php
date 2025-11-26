<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UsersController;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

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
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Generate a unique handle based on the user's name
        $user_handle = '@'.Str::slug($request->name); 
        $counter = 1;

        while (User::where('handle', $user_handle . $counter)->exists()) {
            $counter++;
        }
        $user_handle .= $counter;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'handle' => $user_handle,
            'created_by' => $creator_key,
            'updated_by' => $creator_key,
        ]);

        // create user bank account
        $bnk = (UsersController::class)::createUserBankAccount($user);
 

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
