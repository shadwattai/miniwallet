<?php

namespace App\Helpers;

use Illuminate\Support\Facades\{DB, Log};

class WalletHelper
{
    /**
     * Create initial wallet account for a new user during registration
     * This method bypasses authentication requirements and controllers
     */
    public static function createInitialWallet($user)
    {
        try {
            // Get first active bank
            $bank = DB::table('wlt_banks')
                     ->select('key')
                     ->where('is_active', true)
                     ->first();
            
            if (!$bank) {
                Log::warning('No active banks available for wallet creation');
                return false;
            }

            // Generate unique account number
            $accountNumber = 'WLT' . rand(100000, 999999) . rand(10, 99);
            
            // Check uniqueness
            $attempts = 0;
            while (DB::table('wlt_accounts')->where('account_number', $accountNumber)->exists() && $attempts < 5) {
                $accountNumber = 'WLT' . rand(100000, 999999) . rand(10, 99);
                $attempts++;
            }

            if ($attempts >= 5) {
                Log::error('Failed to generate unique account number');
                return false;
            }

            // Insert initial wallet directly
            $result = DB::table('wlt_accounts')->insert([
                'key' => DB::raw('uuid_generate_v4()'),
                'user_key' => $user->key,
                'bank_key' => $bank->key,
                'account_number' => $accountNumber,
                'account_name' => $user->name . ' Default Account',
                'account_type' => 'wallet',
                'currency' => 'AED',
                'balance' => 0.00,
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'created_by' => $user->key,
                'updated_by' => $user->key,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_by' => null,
                'deleted_at' => null
            ]);

            if ($result) {
                Log::info('Initial wallet created for new user', [
                    'user_key' => $user->key,
                    'account_number' => $accountNumber
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to create initial wallet', [
                'user_key' => $user->key ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}