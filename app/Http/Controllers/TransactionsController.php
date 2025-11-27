<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Hash, Log, Auth, DB};
use Illuminate\Http\Request;
use App\Http\Controllers\Artifacts\CreateController;
use App\Http\Controllers\Artifacts\ReadController;
use App\Http\Controllers\Artifacts\UpdateController;
use App\Http\Controllers\Artifacts\DeleteController;
use App\Http\Controllers\Auth\RegisteredUserController;

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


    private function generateReferenceNumber()
    {
        return 'TXN' . strtoupper(uniqid()) . time();
    }

    public function depositMoney(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'wallet_key' => 'required|string',
                'amount' => 'required|numeric|min:1',
                'description' => 'nullable|string|max:255'
            ]);

            $user = Auth::user();

            // Find the user's wallet and verify ownership
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

            // Find the user's initial account (each user has their own initial account)
            $initial_accounts = $this->readController->SearchRows('wlt_accounts', [
                'user_key' => $user->key,
                'account_type' => 'initial',
                'is_active' => true
            ]);

            if (empty($initial_accounts)) {
                return back()->withErrors(['general' => 'Your initial account not found. Please contact support.']);
            }

            $initial_account = $initial_accounts[0];

            $depositAmount = floatval($validated['amount']);
            $currentBalance = floatval($wallet->balance);
            $newBalance = $currentBalance + $depositAmount;

            // Generate reference number
            $ref_number = $this->generateReferenceNumber();

            // 1. Create the main transaction record
            // CreateController will automatically handle key, created_by, updated_by, created_at, updated_at
            $transactionData = [
                'ref_number' => $ref_number,
                'sender_acct_key' => $initial_account->key,
                'receiver_acct_key' => $wallet->key,
                'description' => $validated['description'] ?? 'Money deposit',
                'type' => 'deposit',
                'amount' => $depositAmount,
                'commission_fee' => 0,
                'status' => 'completed',
                'version' => 1
            ];

            $trxn_key = $this->createController->CreateSingleRow('wlt_transactions', $transactionData);

            if (empty($trxn_key)) {
                return back()->withErrors(['general' => 'Failed to create transaction record.']);
            }

            // 2. Create double-entry transaction details
            // CreateController will automatically handle key, created_by, updated_by, created_at, updated_at
            
            // Credit entry for initial account (money going out of initial account)
            $credit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $initial_account->key,
                'description' => "Deposit from initial account - " . $ref_number,
                'entry' => 'CR',
                'amount_dr' => 0,
                'amount_cr' => $depositAmount,
                'version' => 1
            ];

            $credit_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $credit_entry);

            // Debit entry for savings account (money coming into savings account)
            $debit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $wallet->key,
                'description' => "Deposit to savings account - " . $ref_number,
                'entry' => 'DR',
                'amount_dr' => $depositAmount,
                'amount_cr' => 0,
                'version' => 1
            ];

            $debit_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $debit_entry);

            if (!$credit_detail_key || !$debit_detail_key) {
                // Rollback transaction if detail creation fails
                $this->deleteController->DeleteRow('wlt_transactions', $trxn_key);
                return back()->withErrors(['general' => 'Failed to create transaction details.']);
            }

            // 3. Update the savings account balance using UpdateController
            // UpdateController will automatically handle updated_by and updated_at
            $updateResult = $this->updateController->UpdateSingleRow('wlt_accounts', $validated['wallet_key'], [
                'balance' => $newBalance,
                'version' => $wallet->version + 1
            ]);

            if (!$updateResult) {
                // Rollback if balance update fails
                $this->deleteController->DeleteRow('wlt_transactions', $trxn_key);
                $this->deleteController->DeleteRow('wlt_transactions_details', $credit_detail_key);
                $this->deleteController->DeleteRow('wlt_transactions_details', $debit_detail_key);
                return back()->withErrors(['general' => 'Failed to update wallet balance.']);
            }

            Log::info('Money deposited successfully using double-entry accounting', [
                'ref_number' => $ref_number,
                'user_key' => $user->key,
                'user_name' => $user->name,
                'initial_account_key' => $initial_account->key,
                'savings_account_key' => $wallet->key,
                'amount' => $depositAmount,
                'currency' => $wallet->currency,
                'previous_balance' => $currentBalance,
                'new_balance' => $newBalance,
                'transaction_key' => $trxn_key,
                'credit_detail_key' => $credit_detail_key,
                'debit_detail_key' => $debit_detail_key
            ]);

            return redirect()->route('mywallets')->with('success', [
                'message' => 'Deposit successful',
                'amount' => $depositAmount,
                'currency' => $wallet->currency,
                'account_name' => $wallet->account_name,
                'new_balance' => $newBalance,
                'ref_number' => $ref_number
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
