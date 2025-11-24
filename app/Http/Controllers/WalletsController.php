<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Log, Auth, Hash, DB};

class WalletsController extends Controller
{
    public function getWallets()
    {
        dd('wallets index');

       
        

        return Inertia::render('internals/Wallets', [
            'wallets' => $wallets,
        ]);
    }

}
