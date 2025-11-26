<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Log, Auth, DB};
use App\Http\Controllers\Artifacts\CreateController;
use App\Http\Controllers\Artifacts\ReadController;
use App\Http\Controllers\Artifacts\UpdateController;
use App\Http\Controllers\Artifacts\DeleteController;

class WalletsController extends Controller
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

    public function getWallets()
    {
        try {
            $wallets = $this->readController->GetAllRows('wlt_accounts');

            foreach ($wallets as $index => $wallet) {
                $owner = $this->readController->GetSingleRow('users', ['key' => $wallet->user_key]);
                $wallets[$index]->owner_name = $owner->name ?? 'Unknown';
                $wallets[$index]->owner_email = $owner->email ?? 'Unknown';


                $bank = $this->readController->SearchRows('wlt_banks', ['key' => $wallet->bank_key]);
                $wallets[$index]->bank_name = $bank[0]->bank_name ?? 'Unknown Bank';
            }

            return Inertia::render('wallets/WalletsList', [
                'wallets' => $wallets,
                'User' => Auth::user()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve wallets: ' . $e->getMessage());
            return collect();
        }
    }

    public function getMyWallets()
    {
        try {
            $user = Auth::user();

            $banks = $this->readController->GetAllRows('wlt_banks');
            $wallets = $this->readController->SearchRows('wlt_accounts', ['user_key' => $user->key]);


            foreach ($wallets as $index => $wallet) {
                $owner = $this->readController->GetSingleRow('users', ['key' => $wallet->user_key]);
                $wallets[$index]->owner_name = $owner->name ?? 'Unknown';
                $wallets[$index]->owner_email = $owner->email ?? 'Unknown';


                $bank = $this->readController->SearchRows('wlt_banks', ['key' => $wallet->bank_key]);
                $wallets[$index]->bank_name = $bank[0]->bank_name ?? 'Unknown Bank';
            }

            return Inertia::render('wallets/MyWalletsList', [
                'wallets' => $wallets,
                'User' => Auth::user(),
                'banks' => $banks,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve my wallets: ' . $e->getMessage());
            return Inertia::render('wallets/MyWalletsList', [
                'wallets' => [],
                'User' => Auth::user()
            ]);
        }
    }


    public function createWallet(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'account_name' => 'required|string|max:255',
                'account_type' => 'required|in:wallet,savings',
                'bank_key' => 'required|string',
                'currency' => 'required|string|size:3',
                'initial_balance' => 'nullable|numeric|min:0',
                'is_default' => 'nullable|boolean'
            ]);

            $user = Auth::user();
            
            // Generate unique account number
            $accountNumber = rand(6401, 6899).str_pad(rand(100000, 999999), 8, '0', STR_PAD_LEFT) . rand(10, 99);

            // Check if this should be the default account
            $isDefault = $validated['is_default'] ?? false;
            
            // If this is set as default, update existing wallets to not be default
            if ($isDefault) {
                $existingWallets = $this->readController->SearchRows('wlt_accounts', ['user_key' => $user->key]);
                foreach ($existingWallets as $wallet) {
                    if ($wallet->is_default) {
                        $this->updateController->UpdateSingleRow('wlt_accounts', $wallet->key, [
                            'is_default' => false
                        ]);
                    }
                }
            }

            // Prepare wallet data
            $walletData = [
                'user_key' => $user->key,
                'bank_key' => $validated['bank_key'],
                'account_number' => $accountNumber,
                'account_name' => $validated['account_name'],
                'account_type' => $validated['account_type'],
                'currency' => $validated['currency'],
                'balance' => $validated['initial_balance'] ?? 0.00,
                'is_active' => true,
                'is_default' => $isDefault, 
            ];

            // Create the wallet using CreateController
            $walletKey = $this->createController->CreateSingleRow('wlt_accounts', $walletData);

            if ($walletKey) {
                // Log the wallet creation
                Log::info('Wallet created successfully', [
                    'wallet_key' => $walletKey,
                    'user_key' => $user->key,
                    'account_name' => $validated['account_name'],
                    'account_type' => $validated['account_type']
                ]);

                // Redirect back to MyWalletsList with success message
                return redirect()->route('mywallets')->with('success', [
                    'message' => 'Wallet created successfully',
                    'wallet_key' => $walletKey,
                    'account_number' => $accountNumber
                ]);
            } else {
                Log::error('Failed to create wallet - CreateController returned false', [
                    'user_key' => $user->key,
                    'data' => $walletData
                ]);

                return back()->withErrors([
                    'general' => 'Failed to create wallet'
                ])->withInput();
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Failed to create wallet: ' . $e->getMessage(), [
                'user_key' => Auth::user()?->key,
                'request_data' => $request->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'general' => 'An error occurred while creating the wallet'
            ])->withInput();
        }
    }
}
