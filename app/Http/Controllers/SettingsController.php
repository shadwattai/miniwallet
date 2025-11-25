<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use App\Http\Controllers\Artifacts\ReadController;
use Illuminate\Support\Facades\{Log, Auth, DB};

class SettingsController extends Controller
{
    public function index(Request $request)
    {  
       $banks = (new BankController())->getBanks(); 
       $users = (new UsersController())->getUsers(); 

        return Inertia::render('system/Settings', [
            'users' => $users,
            'banks' => $banks,
            'User' => Auth::user()
        ]);
    } 

}
