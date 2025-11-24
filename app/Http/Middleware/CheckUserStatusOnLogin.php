<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CheckUserStatusOnLogin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only check during login attempts
        if ($request->routeIs('login') && $request->isMethod('POST')) {
            $credentials = $request->only('email', 'password');
            
            // Find user by email first
            $user = \App\Models\User::where('email', $credentials['email'])->first();
            
            // Check if user exists and password is correct before checking status
            if ($user && Hash::check($credentials['password'], $user->password)) {
                if ($user->status === 'inactive') {
                    return redirect()->back()->withErrors([
                        'email' => 'Your account has been deactivated. Please contact support for assistance.',
                    ])->withInput($request->only('email'));
                }
            }
        }

        return $next($request);
    }
}