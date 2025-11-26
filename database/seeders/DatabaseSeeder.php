<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{ 

    /**
     * Seed the application's database.
     */

    public function run(): void
    {  
        DB::table('users')->truncate(); 
        DB::table('wlt_banks')->truncate(); 
        DB::table('wlt_accounts')->truncate(); 

        $masterpass = '$2y$12$LqW3PUWK8Z4We8MXTYnsJ.9sDN.GWfyWYRC9WAe4e5rhhNlP5UTwq';
        $systemRootKey = 'fadf94db-6d13-4328-a7ab-92ee2a1b5090';

        User::factory()->create([
            'key' => $systemRootKey,
            'name' => 'System Root',
            'handle' => '@system.root',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'root@miniwallet.com',
            'phone' => '+255 786 555 665',
            'role' => 'admin',
            'created_by' => $systemRootKey, // Self-created
        ]);

        User::factory()->create([
            'key' => '7d2200c9-1001-413b-a6c4-0e14978a7df4',
            'name' => 'Lisa Wiliams',
            'handle' => '@lisa.wiliams',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'lisa@miniwallet.com',
            'phone' => '+255 752 555 665',
            'role' => 'admin',
            'created_by' => $systemRootKey,
        ]); 
        
        User::factory()->create([
            'key' => '5627f1ac-bda7-477d-ab76-5f4c134a4d39',
            'name' => 'Ada Lovelace',
            'handle' => '@ada.lovelace',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'ada@miniwallet.com',
            'role' => 'user',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Linda Lopez',
            'handle' => '@linda.lopez',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'linda@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Regina Caeli',
            'handle' => '@regina.caeli',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'regina@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Abbie April',
            'handle' => '@abbie.april',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'abbie.april@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);
        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Kemi Badenoch',
            'handle' => '@kemi.badenoch',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'kemi@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Olivia Martin',
            'handle' => '@olivia.martin',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'olivia@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Juddie Wattai',
            'handle' => '@juddie.wattai',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'juddie@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory(10)->create([
            'created_by' => $systemRootKey,
            'status' => 'active',
        ]); 

        // Seed Banks
        $this->seedBanks($systemRootKey);
        
        // Seed Wallet Accounts
        $this->seedWalletAccounts($systemRootKey);
    }

    /**
     * Seed banks data
     */
    private function seedBanks(string $systemRootKey): void
    {
        $banks = [
            [
                'key' => Str::uuid(),
                'bank_name' => 'Emirates NBD Bank',
                'bank_code' => 'ENBD001',
                'bank_logo' => 'enbd.png',
                'swift_code' => 'EBILAEAD',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'Emirates NBD Building, Baniyas Road, Dubai, UAE',
                'phone' => '+971-4-2212121',
                'email' => 'customercare@emiratesnbd.com',
                'website' => 'https://www.emiratesnbd.com',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 1000.00,
                'max_balance' => 50000000.00,
                'daily_transfer_limit' => 1000000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR', 'GBP']),
                'notes' => 'Leading bank in the UAE with comprehensive digital services',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'First Abu Dhabi Bank',
                'bank_code' => 'FAB001',
                'bank_logo' => 'fab.png',
                'swift_code' => 'NBADAEAA',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'FAB Building, Khalifa Street, Abu Dhabi, UAE',
                'phone' => '+971-2-6161411',
                'email' => 'contactcentre@fab.ae',
                'website' => 'https://www.bankfab.com',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 3000.00,
                'max_balance' => 100000000.00,
                'daily_transfer_limit' => 2000000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR', 'GBP', 'CHF']),
                'notes' => 'Largest bank in the UAE by assets',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'Dubai Islamic Bank',
                'bank_code' => 'DIB001',
                'bank_logo' => 'dib.png',
                'swift_code' => 'DUIBAEAA',
                'country_code' => 'UAE',
                'bank_type' => 'islamic',
                'address' => 'DIB Building, Al Ittihad Road, Dubai, UAE',
                'phone' => '+971-4-6092222',
                'email' => 'contactus@dib.ae',
                'website' => 'https://www.dib.ae',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 500.00,
                'max_balance' => 25000000.00,
                'daily_transfer_limit' => 500000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR']),
                'notes' => 'Pioneer in Islamic banking in the UAE',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'Abu Dhabi Commercial Bank',
                'bank_code' => 'ADCB001',
                'bank_logo' => 'adcb.png',
                'swift_code' => 'ADCBAEAA',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'ADCB Building, Sheikh Zayed Street, Abu Dhabi, UAE',
                'phone' => '+971-2-6212030',
                'email' => 'contactcenter@adcb.com',
                'website' => 'https://www.adcb.com',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 1500.00,
                'max_balance' => 75000000.00,
                'daily_transfer_limit' => 1500000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR', 'GBP']),
                'notes' => 'Third largest bank in UAE by assets',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'Mashreq Bank',
                'bank_code' => 'MASH001',
                'bank_logo' => 'mash.png',
                'swift_code' => 'BOMLAEAD',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'Mashreq Bank Building, Al Maktoum Road, Dubai, UAE',
                'phone' => '+971-4-4244444',
                'email' => 'customercare@mashreqbank.com',
                'website' => 'https://www.mashreqbank.com',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 2000.00,
                'max_balance' => 30000000.00,
                'daily_transfer_limit' => 800000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR', 'GBP', 'INR']),
                'notes' => 'Oldest private bank in the UAE',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'RAK Bank',
                'bank_code' => 'RAKB001',
                'bank_logo' => 'rak.png',
                'swift_code' => 'NRAKAEAK',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'RAK Bank Building, Al Qusais Road, Dubai, UAE',
                'phone' => '+971-4-2130000',
                'email' => 'customercare@rakbank.ae',
                'website' => 'https://www.rakbank.ae',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 1000.00,
                'max_balance' => 20000000.00,
                'daily_transfer_limit' => 600000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR']),
                'notes' => 'Dynamic and innovative retail bank',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'Sharjah Islamic Bank',
                'bank_code' => 'SIB001',
                'bank_logo' => 'sib.png',
                'swift_code' => 'SHIUAEAA',
                'country_code' => 'UAE',
                'bank_type' => 'islamic',
                'address' => 'SIB Building, King Abdul Aziz Street, Sharjah, UAE',
                'phone' => '+971-6-5599999',
                'email' => 'info@sib.ae',
                'website' => 'https://www.sib.ae',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 500.00,
                'max_balance' => 15000000.00,
                'daily_transfer_limit' => 400000.00,
                'supported_currencies' => json_encode(['AED', 'USD']),
                'notes' => 'Leading Islamic bank in Sharjah',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'Standard Chartered Bank',
                'bank_code' => 'SCBL001',
                'bank_logo' => 'sc.png',
                'swift_code' => 'SCBLAEAD',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'Standard Chartered Building, Emirates Towers, Dubai, UAE',
                'phone' => '+971-4-5083900',
                'email' => 'uae.service@sc.com',
                'website' => 'https://www.sc.com/ae',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 5000.00,
                'max_balance' => 80000000.00,
                'daily_transfer_limit' => 2500000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR', 'GBP', 'JPY', 'CHF']),
                'notes' => 'International bank with strong presence in UAE',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'HSBC Bank Middle East',
                'bank_code' => 'HSBC001',
                'bank_logo' => 'hsbc.png',
                'swift_code' => 'BBMEAEAD',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'HSBC Building, Gate District, DIFC, Dubai, UAE',
                'phone' => '+971-4-4016000',
                'email' => 'customer.service@hsbc.ae',
                'website' => 'https://www.hsbc.ae',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 10000.00,
                'max_balance' => 100000000.00,
                'daily_transfer_limit' => 3000000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD']),
                'notes' => 'Global banking and financial services provider',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
            [
                'key' => Str::uuid(),
                'bank_name' => 'Citibank UAE',
                'bank_code' => 'CITI001',
                'bank_logo' => 'citi.png',
                'swift_code' => 'CITIAEAD',
                'country_code' => 'UAE',
                'bank_type' => 'commercial',
                'address' => 'Citibank Building, Emirates Towers, Dubai, UAE',
                'phone' => '+971-4-3111234',
                'email' => 'citiphone.uae@citi.com',
                'website' => 'https://www.citibank.ae',
                'is_active' => true,
                'supports_transfers' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'min_balance' => 15000.00,
                'max_balance' => 200000000.00,
                'daily_transfer_limit' => 5000000.00,
                'supported_currencies' => json_encode(['AED', 'USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD']),
                'notes' => 'Premier international banking services',
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ],
        ];

        foreach ($banks as $bank) {
            DB::table('wlt_banks')->insert($bank);
        }

    }

    /**
     * Seed wallet accounts data
     */
    private function seedWalletAccounts(string $systemRootKey): void
    {
        $users = [

            'fadf94db-6d13-4328-a7ab-92ee2a1b5090', // System Root
            '7d2200c9-1001-413b-a6c4-0e14978a7df4', // Lisa Williams
            '5627f1ac-bda7-477d-ab76-5f4c134a4d39', // Ada Lovelace
        ];

        // Get bank keys for seeded banks
        $bankKeys = DB::table('wlt_banks')->pluck('key')->toArray();

        $accountTypes = ['wallet', 'savings', 'checking'];
        $currencies = ['AED', 'USD', 'EUR'];

        $accounts = [];

        // Create accounts for specific users
        foreach ($users as $userKey) {
            // Create 2-3 accounts per user
            $numberOfAccounts = rand(2, 3);
            
            for ($i = 0; $i < $numberOfAccounts; $i++) {
                $bankKey = $bankKeys[array_rand($bankKeys)];
                $accountType = $accountTypes[array_rand($accountTypes)];
                $currency = $currencies[array_rand($currencies)];
                
                $accounts[] = [
                    'key' => Str::uuid(),
                    'user_key' => $userKey,
                    'bank_key' => $bankKey,
                    'account_number' => '6401'.str_pad(rand(100000, 999999), 8, '0', STR_PAD_LEFT) . rand(10, 99),
                    'account_name' => $this->generateAccountName($accountType),
                    'account_type' => $accountType,
                    'currency' => $currency,
                    'balance' => $this->generateBalance($accountType),
                    'is_active' => rand(0, 10) > 1, // 90% chance of being active
                    'is_default' => $i === 0, // First account is default
                    'created_by' => $systemRootKey,
                    'updated_by' => $systemRootKey,
                ];
            }
        }

        // Create a few more random accounts for other users
        $otherUsers = DB::table('users')
            ->whereNotIn('key', $users)
            ->where('key', '!=', $systemRootKey)
            ->limit(5)
            ->pluck('key')
            ->toArray();

        foreach ($otherUsers as $userKey) {
            $bankKey = $bankKeys[array_rand($bankKeys)];
            $accountType = $accountTypes[array_rand($accountTypes)];
            $currency = $currencies[array_rand($currencies)];
            
            $accounts[] = [
                'key' => Str::uuid(),
                'user_key' => $userKey,
                'bank_key' => $bankKey,
                'account_number' => '6501'.str_pad(rand(100000, 999999), 8, '0', STR_PAD_LEFT) . rand(10, 99),
                'account_name' => $this->generateAccountName($accountType),
                'account_type' => $accountType,
                'currency' => $currency,
                'balance' => $this->generateBalance($accountType),
                'is_active' => rand(0, 10) > 1, // 90% chance of being active
                'is_default' => true, // Only account, so it's default
                'created_by' => $systemRootKey,
                'updated_by' => $systemRootKey,
            ];
        }

        foreach ($accounts as $account) {
            DB::table('wlt_accounts')->insert($account);
        }
    }

    /**
     * Generate account name based on type
     */
    private function generateAccountName(string $accountType): string
    {
        $names = [
            'wallet' => ['Main Wallet', 'Personal Wallet', 'Digital Wallet', 'Primary Wallet'],
            'savings' => ['Emergency Fund', 'Vacation Savings', 'Home Savings', 'Future Plans'],
            'checking' => ['Daily Expenses', 'Business Account', 'Monthly Budget', 'Primary Checking'],
        ];

        return $names[$accountType][array_rand($names[$accountType])];
    }

    /**
     * Generate realistic balance based on account type
     */
    private function generateBalance(string $accountType): float
    {
        switch ($accountType) {
            case 'wallet':
                return rand(100, 5000) + (rand(0, 99) / 100); // 100 to 5000 with cents
            case 'savings':
                return rand(5000, 50000) + (rand(0, 99) / 100); // 5000 to 50000 with cents
            case 'checking':
                return rand(500, 15000) + (rand(0, 99) / 100); // 500 to 15000 with cents
            default:
                return rand(100, 1000) + (rand(0, 99) / 100);
        }
    }
}