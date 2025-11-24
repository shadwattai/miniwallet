<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Log, Auth, Hash, DB};

class TransactionsController extends Controller
{
    public function getTransactions()
    {
        $transactions = [];



        return Inertia::render('transactions/List', [
            'User' => Auth::user(),
            'transactions' => $transactions,
        ]);
    }

}
