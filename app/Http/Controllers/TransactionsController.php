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
        $user = Auth::user();

        // Fetch transactions for the authenticated user with user and account information
        $transactions = DB::table('wlt_transactions as t')
            ->leftJoin('wlt_accounts as sender_acc', 't.sender_acct_key', '=', 'sender_acc.key')
            ->leftJoin('wlt_accounts as receiver_acc', 't.receiver_acct_key', '=', 'receiver_acc.key')
            ->leftJoin('users as sender_user', 'sender_acc.user_key', '=', 'sender_user.key')
            ->leftJoin('users as receiver_user', 'receiver_acc.user_key', '=', 'receiver_user.key')
            ->select(
                't.key',
                't.ref_number',
                't.sender_acct_key',
                't.receiver_acct_key',
                't.description',
                't.type',
                't.amount',
                't.commission_fee',
                't.status',
                't.created_at',
                't.updated_at',
                'sender_acc.account_name as sender_account_name',
                'receiver_acc.account_name as receiver_account_name',
                'sender_acc.currency',
                'sender_user.name as sender_user_name',
                'receiver_user.name as receiver_user_name'
            )
            ->where(function($query) use ($user) {
                $query->where('sender_acc.user_key', $user->key)
                      ->orWhere('receiver_acc.user_key', $user->key);
            })
            ->orderBy('t.created_at', 'desc')
            ->get();

        return Inertia::render('transactions/TransactionsList', [
            'User' => $user,
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

    public function withdrawMoney(Request $request)
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
                return back()->withErrors(['general' => 'Wallet not found or you do not have permission to withdraw from it.']);
            }

            $wallet = $wallets[0];

            // Check if wallet is active
            if (!$wallet->is_active) {
                return back()->withErrors(['general' => 'Cannot withdraw from an inactive wallet.']);
            }

            // Check if this is a savings account (only savings can withdraw)
            if ($wallet->account_type !== 'savings') {
                return back()->withErrors(['general' => 'Withdrawals can only be made from savings accounts.']);
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

            $withdrawAmount = floatval($validated['amount']);
            $currentBalance = floatval($wallet->balance);
            $newBalance = $currentBalance - $withdrawAmount;

            // Check if sufficient funds available
            if ($withdrawAmount > $currentBalance) {
                return back()->withErrors(['amount' => 'Insufficient funds for withdrawal.']);
            }

            // Check minimum balance requirement (e.g., AED 100 must remain)
            $minBalance = 100; // Minimum balance requirement
            if ($newBalance < $minBalance) {
                return back()->withErrors(['amount' => "Withdrawal would violate minimum balance requirement. Minimum {$wallet->currency} {$minBalance} must remain in account."]);
            }

            // Generate reference number
            $ref_number = 'WD' . strtoupper(uniqid()) . time();

            // 1. Create the main transaction record
            // For withdrawals: Money flows FROM user's savings TO user's initial account
            $transactionData = [
                'ref_number' => $ref_number,
                'sender_acct_key' => $wallet->key,         // User's savings account as sender
                'receiver_acct_key' => $initial_account->key,  // User's initial account as receiver
                'description' => $validated['description'] ?? 'Money withdrawal',
                'type' => 'withdrawal',
                'amount' => $withdrawAmount,
                'commission_fee' => 0,
                'status' => 'completed',
                'version' => 1
            ];

            $trxn_key = $this->createController->CreateSingleRow('wlt_transactions', $transactionData);

            if (empty($trxn_key)) {
                return back()->withErrors(['general' => 'Failed to create transaction record.']);
            }

            // 2. Create double-entry transaction details (opposite of deposit)
            
            // Credit entry for savings account (money going out of savings account)
            $credit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $wallet->key,
                'description' => "Withdrawal from savings account - " . $ref_number,
                'entry' => 'CR',
                'amount_dr' => 0,
                'amount_cr' => $withdrawAmount,
                'version' => 1
            ];

            $credit_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $credit_entry);

            // Debit entry for initial account (money coming into initial account)
            $debit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $initial_account->key,
                'description' => "Withdrawal to initial account - " . $ref_number,
                'entry' => 'DR',
                'amount_dr' => $withdrawAmount,
                'amount_cr' => 0,
                'version' => 1
            ];

            $debit_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $debit_entry);

            if (!$credit_detail_key || !$debit_detail_key) {
                // Rollback transaction if detail creation fails
                $this->deleteController->DeleteRow('wlt_transactions', $trxn_key);
                return back()->withErrors(['general' => 'Failed to create transaction details.']);
            }

            // 3. Update the savings account balance (subtract withdrawal amount)
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

            Log::info('Money withdrawn successfully using double-entry accounting', [
                'ref_number' => $ref_number,
                'user_key' => $user->key,
                'user_name' => $user->name,
                'savings_account_key' => $wallet->key,
                'initial_account_key' => $initial_account->key,
                'amount' => $withdrawAmount,
                'currency' => $wallet->currency,
                'previous_balance' => $currentBalance,
                'new_balance' => $newBalance,
                'transaction_key' => $trxn_key,
                'credit_detail_key' => $credit_detail_key,
                'debit_detail_key' => $debit_detail_key
            ]);

            return redirect()->route('mywallets')->with('success', [
                'message' => 'Withdrawal successful',
                'amount' => $withdrawAmount,
                'currency' => $wallet->currency,
                'account_name' => $wallet->account_name,
                'new_balance' => $newBalance,
                'ref_number' => $ref_number
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to withdraw money', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_key' => Auth::user()->key ?? 'unknown'
            ]);

            return back()->withErrors(['general' => 'Failed to withdraw money. Please try again.'])->withInput();
        }
    }

    public function topUpWallet(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'wallet_key' => 'required|string',
                'source_account_key' => 'required|string',
                'amount' => 'required|numeric|min:1',
                'description' => 'nullable|string|max:255'
            ]);

            $user = Auth::user();

            // Find the target digital wallet (receiver)
            $target_wallets = $this->readController->SearchRows('wlt_accounts', [
                'key' => $validated['wallet_key'],
                'user_key' => $user->key
            ]);

            if (empty($target_wallets)) {
                return back()->withErrors(['general' => 'Target wallet not found or you do not have permission to top up this wallet.']);
            }

            $target_wallet = $target_wallets[0];

            // Check if target wallet is active and is a digital wallet
            if (!$target_wallet->is_active) {
                return back()->withErrors(['general' => 'Cannot top up an inactive wallet.']);
            }

            if ($target_wallet->account_type !== 'wallet') {
                return back()->withErrors(['general' => 'Top up can only be done to digital wallet accounts.']);
            }

            // Find the source savings account (sender)
            $source_accounts = $this->readController->SearchRows('wlt_accounts', [
                'key' => $validated['source_account_key'],
                'user_key' => $user->key
            ]);

            if (empty($source_accounts)) {
                return back()->withErrors(['general' => 'Source account not found or you do not have permission to use this account.']);
            }

            $source_account = $source_accounts[0];

            // Check if source account is active and is a savings account
            if (!$source_account->is_active) {
                return back()->withErrors(['general' => 'Cannot transfer from an inactive savings account.']);
            }

            if ($source_account->account_type !== 'savings') {
                return back()->withErrors(['general' => 'Top up can only be done from savings accounts.']);
            }

            // Check currency matching
            if ($source_account->currency !== $target_wallet->currency) {
                return back()->withErrors(['general' => 'Currency mismatch between source and target accounts.']);
            }

            $topUpAmount = floatval($validated['amount']);
            $sourceCurrentBalance = floatval($source_account->balance);
            $targetCurrentBalance = floatval($target_wallet->balance);

            // Check if source account has sufficient balance (including minimum balance requirement)
            $minBalance = 100; // AED 100 minimum balance
            $availableBalance = $sourceCurrentBalance - $minBalance;

            if ($availableBalance < $topUpAmount) {
                return back()->withErrors(['amount' => "Insufficient funds. Available for transfer: {$source_account->currency} " . number_format($availableBalance, 2) . " (AED 100 minimum must remain)."]);
            }

            // Calculate new balances
            $newSourceBalance = $sourceCurrentBalance - $topUpAmount;
            $newTargetBalance = $targetCurrentBalance + $topUpAmount;

            // Generate reference number with TU prefix
            $ref_number = 'TU' . strtoupper(uniqid()) . time();

            // 1. Create the main transaction record
            // Following exact wlt_transactions schema
            $transactionData = [
                'ref_number' => $ref_number,
                'sender_acct_key' => $source_account->key,    // Savings account (source)
                'receiver_acct_key' => $target_wallet->key,   // Digital wallet (destination)
                'description' => $validated['description'] ?? 'Wallet top up transfer',
                'type' => 'topup',
                'amount' => $topUpAmount,
                'commission_fee' => 0,
                'status' => 'completed',
                'version' => 1
            ];

            $trxn_key = $this->createController->CreateSingleRow('wlt_transactions', $transactionData);

            if (empty($trxn_key)) {
                return back()->withErrors(['general' => 'Failed to create transaction record.']);
            }

            // 2. Create double-entry transaction details
            // Following exact wlt_transactions_details schema
            
            // Credit entry for source account (money going out of savings)
            $credit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $source_account->key,
                'description' => "Top up transfer out - " . $ref_number,
                'entry' => 'CR',
                'amount_dr' => 0,              // Must be 0 for CR entry
                'amount_cr' => $topUpAmount,   // Credit amount (money going out)
                'version' => 1
            ];

            $credit_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $credit_entry);

            // Debit entry for target wallet (money coming into digital wallet)
            $debit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $target_wallet->key,
                'description' => "Top up transfer in - " . $ref_number,
                'entry' => 'DR',
                'amount_dr' => $topUpAmount,   // Debit amount (money coming in)
                'amount_cr' => 0,              // Must be 0 for DR entry
                'version' => 1
            ];

            $debit_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $debit_entry);

            if (!$credit_detail_key || !$debit_detail_key) {
                // Rollback transaction if detail creation fails
                $this->deleteController->DeleteRow('wlt_transactions', $trxn_key);
                return back()->withErrors(['general' => 'Failed to create transaction details.']);
            }

            // 3. Update both account balances
            // Update source account (subtract amount)
            $sourceUpdateResult = $this->updateController->UpdateSingleRow('wlt_accounts', $validated['source_account_key'], [
                'balance' => $newSourceBalance,
                'version' => $source_account->version + 1
            ]);

            // Update target wallet (add amount)
            $targetUpdateResult = $this->updateController->UpdateSingleRow('wlt_accounts', $validated['wallet_key'], [
                'balance' => $newTargetBalance,
                'version' => $target_wallet->version + 1
            ]);

            if (!$sourceUpdateResult || !$targetUpdateResult) {
                // Rollback if balance updates fail
                $this->deleteController->DeleteRow('wlt_transactions', $trxn_key);
                $this->deleteController->DeleteRow('wlt_transactions_details', $credit_detail_key);
                $this->deleteController->DeleteRow('wlt_transactions_details', $debit_detail_key);
                return back()->withErrors(['general' => 'Failed to update account balances.']);
            }

            Log::info('Wallet top up transfer completed successfully', [
                'ref_number' => $ref_number,
                'user_key' => $user->key,
                'user_name' => $user->name,
                'source_account_key' => $source_account->key,
                'source_account_name' => $source_account->account_name,
                'target_wallet_key' => $target_wallet->key,
                'target_wallet_name' => $target_wallet->account_name,
                'amount' => $topUpAmount,
                'currency' => $target_wallet->currency,
                'source_previous_balance' => $sourceCurrentBalance,
                'source_new_balance' => $newSourceBalance,
                'target_previous_balance' => $targetCurrentBalance,
                'target_new_balance' => $newTargetBalance,
                'transaction_key' => $trxn_key,
                'credit_detail_key' => $credit_detail_key,
                'debit_detail_key' => $debit_detail_key
            ]);

            return redirect()->route('mywallets')->with('success', [
                'message' => 'Top up successful',
                'amount' => $topUpAmount,
                'currency' => $target_wallet->currency,
                'source_account' => $source_account->account_name,
                'target_wallet' => $target_wallet->account_name,
                'new_target_balance' => $newTargetBalance,
                'new_source_balance' => $newSourceBalance,
                'ref_number' => $ref_number
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to process wallet top up', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_key' => Auth::user()->key ?? 'unknown'
            ]);

            return back()->withErrors(['general' => 'Failed to process top up transfer. Please try again.'])->withInput();
        }
    }

    public function transferMoney(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'sender_wallet_key' => 'required|string',
                'receiver_wallet_key' => 'required|string',
                'amount' => 'required|numeric|min:1',
                'description' => 'nullable|string|max:255'
            ]);

            $user = Auth::user();

            // Find sender wallet (must belong to authenticated user)
            $sender_wallets = $this->readController->SearchRows('wlt_accounts', [
                'key' => $validated['sender_wallet_key'],
                'user_key' => $user->key
            ]);

            if (empty($sender_wallets)) {
                return back()->withErrors(['general' => 'Sender wallet not found or you do not have permission to use this wallet.']);
            }

            $sender_wallet = $sender_wallets[0];

            // Check if sender wallet is active and is a digital wallet
            if (!$sender_wallet->is_active) {
                return back()->withErrors(['general' => 'Cannot transfer from an inactive wallet.']);
            }

            if ($sender_wallet->account_type !== 'wallet') {
                return back()->withErrors(['general' => 'Transfers can only be made from digital wallet accounts.']);
            }

            // Find receiver wallet (can belong to any user)
            $receiver_wallets = $this->readController->SearchRows('wlt_accounts', [
                'key' => $validated['receiver_wallet_key']
            ]);

            if (empty($receiver_wallets)) {
                return back()->withErrors(['general' => 'Receiver wallet not found.']);
            }

            $receiver_wallet = $receiver_wallets[0];

            // Check if receiver wallet is active and is a digital wallet
            if (!$receiver_wallet->is_active) {
                return back()->withErrors(['general' => 'Cannot transfer to an inactive wallet.']);
            }

            if ($receiver_wallet->account_type !== 'wallet') {
                return back()->withErrors(['general' => 'Transfers can only be made to digital wallet accounts.']);
            }

            // Cannot transfer to same wallet
            if ($sender_wallet->key === $receiver_wallet->key) {
                return back()->withErrors(['general' => 'Cannot transfer to the same wallet.']);
            }

            // Check currency matching
            if ($sender_wallet->currency !== $receiver_wallet->currency) {
                return back()->withErrors(['general' => 'Currency mismatch between sender and receiver wallets.']);
            }

            $transferAmount = floatval($validated['amount']);
            $commissionRate = 0.015; // 1.5%
            $commissionFee = $transferAmount * $commissionRate;
            $totalDebitAmount = $transferAmount + $commissionFee; // Total amount to debit from sender

            $senderCurrentBalance = floatval($sender_wallet->balance);
            $receiverCurrentBalance = floatval($receiver_wallet->balance);

            // Check if sender has sufficient balance for transfer + commission
            if ($senderCurrentBalance < $totalDebitAmount) {
                return back()->withErrors([
                    'amount' => 'Insufficient funds. Required: ' . $sender_wallet->currency . ' ' . number_format($totalDebitAmount, 2) . 
                              ' (Transfer: ' . number_format($transferAmount, 2) . ' + Commission: ' . number_format($commissionFee, 2) . ').'
                ]);
            }

            // Find system root user's initial account for commission
            $root_user = $this->readController->SearchRows('users', [
                'email' => 'root@miniwallet.com'
            ]);

            if (empty($root_user)) {
                return back()->withErrors(['general' => 'System root account not found.']);
            }

            $system_accounts = $this->readController->SearchRows('wlt_accounts', [
                'user_key' => $root_user[0]->key,
                'account_type' => 'initial',
                'is_active' => true
            ]);

            if (empty($system_accounts)) {
                return back()->withErrors(['general' => 'System commission account not found.']);
            }

            $system_account = $system_accounts[0];

            // Calculate new balances
            $newSenderBalance = $senderCurrentBalance - $totalDebitAmount;
            $newReceiverBalance = $receiverCurrentBalance + $transferAmount;

            // Generate reference number
            $ref_number = 'TR' . strtoupper(uniqid()) . time();

            // 1. Create the main transaction record
            $transactionData = [
                'ref_number' => $ref_number,
                'sender_acct_key' => $sender_wallet->key,
                'receiver_acct_key' => $receiver_wallet->key,
                'description' => $validated['description'] ?? 'Wallet to wallet transfer',
                'type' => 'transfer',
                'amount' => $transferAmount,
                'commission_fee' => $commissionFee,
                'status' => 'completed',
                'version' => 1
            ];

            $trxn_key = $this->createController->CreateSingleRow('wlt_transactions', $transactionData);

            if (empty($trxn_key)) {
                return back()->withErrors(['general' => 'Failed to create transaction record.']);
            }

            // 2. Create transaction details (3 entries for transfer with commission)
            
            // Entry 1: Credit sender wallet (total amount out: transfer + commission)
            $sender_credit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $sender_wallet->key,
                'description' => "Transfer out (incl. commission) - " . $ref_number,
                'entry' => 'CR',
                'amount_dr' => 0,
                'amount_cr' => $totalDebitAmount, // Transfer amount + commission
                'version' => 1
            ];

            $sender_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $sender_credit_entry);

            // Entry 2: Debit receiver wallet (transfer amount only)
            $receiver_debit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $receiver_wallet->key,
                'description' => "Transfer received - " . $ref_number,
                'entry' => 'DR',
                'amount_dr' => $transferAmount, // Only the transfer amount
                'amount_cr' => 0,
                'version' => 1
            ];

            $receiver_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $receiver_debit_entry);

            // Entry 3: Debit system account (commission fee)
            $commission_debit_entry = [
                'trxn_key' => $trxn_key,
                'acct_key' => $system_account->key,
                'description' => "Commission fee - " . $ref_number,
                'entry' => 'DR',
                'amount_dr' => $commissionFee, // Commission amount
                'amount_cr' => 0,
                'version' => 1
            ];

            $commission_detail_key = $this->createController->CreateSingleRow('wlt_transactions_details', $commission_debit_entry);

            if (!$sender_detail_key || !$receiver_detail_key || !$commission_detail_key) {
                // Rollback transaction if detail creation fails
                $this->deleteController->DeleteRow('wlt_transactions', $trxn_key);
                return back()->withErrors(['general' => 'Failed to create transaction details.']);
            }

            // 3. Update wallet balances
            
            // Update sender wallet (subtract total amount)
            $senderUpdateResult = $this->updateController->UpdateSingleRow('wlt_accounts', $validated['sender_wallet_key'], [
                'balance' => $newSenderBalance,
                'version' => $sender_wallet->version + 1
            ]);

            // Update receiver wallet (add transfer amount)
            $receiverUpdateResult = $this->updateController->UpdateSingleRow('wlt_accounts', $validated['receiver_wallet_key'], [
                'balance' => $newReceiverBalance,
                'version' => $receiver_wallet->version + 1
            ]);

            if (!$senderUpdateResult || !$receiverUpdateResult) {
                // Rollback if balance updates fail
                $this->deleteController->DeleteRow('wlt_transactions', $trxn_key);
                $this->deleteController->DeleteRow('wlt_transactions_details', $sender_detail_key);
                $this->deleteController->DeleteRow('wlt_transactions_details', $receiver_detail_key);
                $this->deleteController->DeleteRow('wlt_transactions_details', $commission_detail_key);
                return back()->withErrors(['general' => 'Failed to update wallet balances.']);
            }

            // Get receiver user info for logging
            $receiver_user = $this->readController->SearchRows('users', [
                'key' => $receiver_wallet->user_key
            ])[0] ?? null;

            Log::info('Wallet transfer completed successfully with commission', [
                'ref_number' => $ref_number,
                'sender_user_key' => $user->key,
                'sender_user_name' => $user->name,
                'receiver_user_key' => $receiver_wallet->user_key,
                'receiver_user_name' => $receiver_user->name ?? 'Unknown',
                'sender_wallet_key' => $sender_wallet->key,
                'sender_wallet_name' => $sender_wallet->account_name,
                'receiver_wallet_key' => $receiver_wallet->key,
                'receiver_wallet_name' => $receiver_wallet->account_name,
                'transfer_amount' => $transferAmount,
                'commission_fee' => $commissionFee,
                'total_debited' => $totalDebitAmount,
                'currency' => $sender_wallet->currency,
                'sender_previous_balance' => $senderCurrentBalance,
                'sender_new_balance' => $newSenderBalance,
                'receiver_previous_balance' => $receiverCurrentBalance,
                'receiver_new_balance' => $newReceiverBalance,
                'transaction_key' => $trxn_key,
                'system_account_key' => $system_account->key
            ]);

            return redirect()->route('mywallets')->with('success', 'Transfer completed successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to process wallet transfer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_key' => Auth::user()->key ?? 'unknown'
            ]);

            return back()->withErrors(['general' => 'Failed to process transfer. Please try again.'])->withInput();
        }
    }

    public function searchWallets(Request $request)
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:2',
                'exclude_wallet' => 'nullable|string',
                'currency' => 'nullable|string'
            ]);

            $query = $validated['query'];
            $excludeWallet = $validated['exclude_wallet'] ?? null;
            $currency = $validated['currency'] ?? null;

            // First, search for users by name, email, or handle
            $users = DB::table('users')
                ->select('key', 'name', 'email', 'handle')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->where(function($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%")
                      ->orWhere('handle', 'like', "%{$query}%");
                })
                ->limit(10) // Limit users to avoid too many results
                ->get();

            $wallets = collect();

            // For each matching user, get their wallet accounts
            foreach ($users as $user) {
                $userWallets = DB::table('wlt_accounts')
                    ->select(
                        'key',
                        'user_key',
                        'account_name', 
                        'account_number',
                        'currency',
                        'balance'
                    )
                    ->where('user_key', $user->key)
                    ->where('account_type', 'wallet')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->when($excludeWallet, function($q, $exclude) {
                        return $q->where('key', '!=', $exclude);
                    })
                    ->when($currency, function($q, $curr) {
                        return $q->where('currency', $curr);
                    })
                    ->get()
                    ->map(function($wallet) use ($user) {
                        $wallet->user_name = $user->name;
                        $wallet->user_email = $user->email;
                        $wallet->user_handle = $user->handle;
                        return $wallet;
                    });

                $wallets = $wallets->concat($userWallets);
            }

            // Also search directly by account name or number for better coverage
            $directWallets = DB::table('wlt_accounts')
                ->join('users', 'wlt_accounts.user_key', '=', 'users.key')
                ->select(
                    'wlt_accounts.key',
                    'wlt_accounts.user_key',
                    'wlt_accounts.account_name', 
                    'wlt_accounts.account_number',
                    'wlt_accounts.currency',
                    'wlt_accounts.balance',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.handle as user_handle'
                )
                ->where('wlt_accounts.account_type', 'wallet')
                ->where('wlt_accounts.is_active', true)
                ->where('users.status', 'active')
                ->whereNull('wlt_accounts.deleted_at')
                ->whereNull('users.deleted_at')
                ->where(function($q) use ($query) {
                    $q->where('wlt_accounts.account_name', 'like', "%{$query}%")
                      ->orWhere('wlt_accounts.account_number', 'like', "%{$query}%");
                })
                ->when($excludeWallet, function($q, $exclude) {
                    return $q->where('wlt_accounts.key', '!=', $exclude);
                })
                ->when($currency, function($q, $curr) {
                    return $q->where('wlt_accounts.currency', $curr);
                })
                ->get();

            // Merge and remove duplicates
            $allWallets = $wallets->concat($directWallets)
                ->unique('key')
                ->take(20) // Limit final results
                ->values();

            return response()->json([
                'wallets' => $allWallets,
                'total' => $allWallets->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to search wallets', [
                'error' => $e->getMessage(),
                'user_key' => Auth::user()->key ?? 'unknown'
            ]);

            return response()->json(['error' => 'Failed to search wallets.'], 500);
        }
    }
}
