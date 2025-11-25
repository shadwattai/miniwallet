<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use App\Http\Controllers\Controller;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Artifacts\ReadController;
use Illuminate\Support\Facades\{Log, Auth, DB};

class SettingsController extends Controller
{
    public function index()
    { 
       $users = (new UsersController())->getUsers(); 
       $banks = $this->getBanks();

        return Inertia::render('system/Settings', [
            'User' => Auth::user(),
            'users' => $users,
            'banks' => $banks,
        ]);
    }

    private function getBanks()
    {
        try {
            $banks = (new ReadController())->GetAllRows('wlt_banks');
            
            return $banks;
        } catch (\Exception $e) {
            Log::error('Error fetching banks: ' . $e->getMessage());
            return [];
        }
    }

}
