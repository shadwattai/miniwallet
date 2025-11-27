<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Auth, DB}; 

class HomeController extends Controller
{
    public function getTransactionStatistics()
    {
        $user = Auth::user();

        // Fetch transaction statistics for the authenticated user
        $statistics = DB::table('wlt_transactions')
            ->select(
                DB::raw('type'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where(function ($query) use ($user) {
                $query->where('sender_acct_key', $user->key)
                      ->orWhere('receiver_acct_key', $user->key);
            })
            ->groupBy('type')
            ->get();

        return $statistics;
    }

    public function getAccountBalance()
    {
        $user = Auth::user();

        $balance = (float) DB::table('wlt_accounts')
            ->where('user_key', $user->key)
            ->where('account_type', 'wallet')
            ->sum('balance');

        return $balance;
    }

    public function getRecentTransactions()
    {
        $user = Auth::user();

        $recentTransactions = DB::table('wlt_transactions')
            ->where(function ($query) use ($user) {
                $query->where('sender_acct_key', $user->key)
                      ->orWhere('receiver_acct_key', $user->key);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return $recentTransactions;
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $accountBalance = $this->getAccountBalance();
        $recentTransactions = $this->getRecentTransactions();
        $transactionStatistics = $this->getTransactionStatistics();

        return Inertia::render('Home', [
            'User' => $user,
            'AccountBalance' => $accountBalance,
            'RecentTransactions' => $recentTransactions,
            'TransactionStatistics' => $transactionStatistics,
        ]);
    }

    
}
