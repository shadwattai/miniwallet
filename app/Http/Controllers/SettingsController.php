<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Log, Auth, Hash, DB};

class SettingsController extends Controller
{
    public function getSettings()
    {
       $settings = [];



        return Inertia::render('system/List', [
            'User' => Auth::user(),
            'settings' => $settings,
        ]);
    }

}
