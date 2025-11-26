<?php

namespace App\Http\Controllers;

use Inertia\Inertia; 
use App\Http\Controllers\Controller; 
use Illuminate\Support\Facades\{Hash, Log, Auth, DB};
use App\Http\Controllers\Artifacts\CreateController;
use App\Http\Controllers\Artifacts\ReadController;
use App\Http\Controllers\Artifacts\UpdateController;
use App\Http\Controllers\Artifacts\DeleteController;

class TransactionsController extends Controller
{
    private $readController;
    private $createController;
    private $updateController;
    private $deleteController;

    public function __construct()
    {
        $this->readController = new ReadController();
        $this->createController = new CreateController();
        $this->updateController = new UpdateController();
        $this->deleteController = new DeleteController();
    }

    public function getTransactions()
    {
        $transactions = [];



        return Inertia::render('transactions/List', [
            'User' => Auth::user(),
            'transactions' => $transactions,
        ]);
    }



    public function depositMoney(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'wallet_key' => 'required|string',
                'amount' => 'required|numeric|min:1|max:1000000',
                'description' => 'nullable|string|max:255'
            ]);

            $user = Auth::user();

            // Find the wallet and verify ownership
            $wallets = $this->readController->SearchRows('wlt_accounts', [
                'key' => $validated['wallet_key'],
                'user_key' => $user->key
            ]);

            if (empty($wallets)) {
                return back()->withErrors(['general' => 'Wallet not found or you do not have permission to deposit to it.']);
            }

            $wallet = $wallets[0];

            // Check if wallet is active
            if (!$wallet->is_active) {
                return back()->withErrors(['general' => 'Cannot deposit to an inactive wallet.']);
            }

            // Check if this is a savings account (only savings can receive deposits)
            if ($wallet->account_type !== 'savings') {
                return back()->withErrors(['general' => 'Deposits can only be made to savings accounts.']);
            }

            // Calculate new balance
            $currentBalance = floatval($wallet->balance);
            $depositAmount = floatval($validated['amount']);
            $newBalance = $currentBalance + $depositAmount;

            // Check if new balance would exceed maximum (optional bank limit check)
            $maxBalance = 10000000; // Default max balance
            if ($newBalance > $maxBalance) {
                return back()->withErrors(['amount' => "Deposit would exceed maximum balance limit of {$wallet->currency} {$maxBalance}."]);
            }

            // Update wallet balance
            $this->updateController->UpdateSingleRow('wlt_accounts', $validated['wallet_key'], [
                'balance' => $newBalance,
                'updated_by' => $user->key
            ]);

            Log::info('Money deposited to wallet', [
                'wallet_key' => $validated['wallet_key'],
                'account_name' => $wallet->account_name,
                'amount' => $depositAmount,
                'currency' => $wallet->currency,
                'previous_balance' => $currentBalance,
                'new_balance' => $newBalance,
                'user_key' => $user->key,
                'user_name' => $user->name,
                'description' => $validated['description']
            ]);

            return redirect()->route('mywallets')->with('success', [
                'message' => 'Deposit successful',
                'amount' => $depositAmount,
                'currency' => $wallet->currency,
                'account_name' => $wallet->account_name,
                'new_balance' => $newBalance
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to deposit money', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_key' => Auth::user()->key ?? 'unknown'
            ]);
            
            return back()->withErrors(['general' => 'Failed to deposit money. Please try again.'])->withInput();
        }
    }

}
