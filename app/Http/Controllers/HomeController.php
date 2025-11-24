<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Auth, DB}; 

class HomeController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user(); 

        if ($user->role === 'admin') {
             
        } 
        else {
             
        }

        return Inertia::render('personal/Home', [
            'User' => $user,
        ]);
    }

    
}
