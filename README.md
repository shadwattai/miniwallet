# miniwallet
Miniwallet Web App 

## Installation guide
1. composer install

2. php artisan migrate

3. php artisan db:seed
Will insert 

(i) the root/admin user
email: root@miniwallet.com
password: masterpass

(ii) the initial/invisible account
helps to DR and CR when we Deposit and Withdraw from Savings Accounts

(iii) an initial list of banks to maintain our savings accounts

## Tech stack
PHP 8.4, 
Laravel 12.32, 
PostgreSQL 17, 
Vue 3 + Composition API, and
PrimeVue CSS component library.

## File structure
Follows oppinionated structure from the Laravel starter kit

## Artifacts API
I created a comprehensive REST-ish API with sanitized reusable methods for common CRUD operations
Controller classes are found in the Controllers/Artifacts directory and are callable globally in the system. 


## Operations

1. When User logs in
- Going to My Wallets, they can create
(i) a savings account mapped to the bank (to allow deposits).
(ii) a digital wallet account that can transfer to other wallets(to allow transfers)

2. Initial Deposit transaction
Make a deposit to the savings account
this will credit the initial account(invisible) and debit the savings account 

3. Withdraw transaction
Make a withdraw to the savings account
this will credit the savings account and debit the initial account(invisible) thus reducing amount from the savings account

4. Topup transaction
Make a Topup to the digital wallet account
this will credit the savings account and debit the WALLET account thus reducing amount from the savings account and increasing amounts to the WALLET account.

5. Transfer transaction
If the Wallet account has money with amount bigger than minimum, the amount exceeding minimum can be transfered to any othe wallet account found in our miniwallet app.


## Database structure 
1. Core Application Tables
users
Use: Store user accounts and authentication information
Purpose: Main user registry for the application
Contains: User credentials, profile info, roles, and account status
Key Fields: key (UUID), name, email, password, handle, role, status

wlt_banks
Use: Master list of supported banks
Purpose: Reference data for savings accounts creation
Contains: Bank information, SWIFT codes, currencies, logos
Seeded: Pre-populated with initial bank list during installation
Key Fields: key (UUID), name, short_name, swift_code, country, currency

wlt_accounts
Use: Store all user accounts (savings, wallets, and initial accounts)
Purpose: Central account registry with three types:
Initial Accounts: Invisible system accounts for deposit/withdrawal flows
Savings Accounts: Bank-linked accounts that can receive deposits and make withdrawals
Digital Wallet Accounts: Digital wallets for transfers between users
Key Fields: key (UUID), user_key, bank_key, account_type, balance, currency

2. Transaction Tables (Double-Entry Accounting)
wlt_transactions
Use: Main transaction registry
Purpose: Store high-level transaction information for all money movements
Contains: Transaction metadata, sender/receiver accounts, amounts, status

Transaction Types:
deposit - Money coming into savings from external source
withdrawal - Money leaving savings to external destination
topup - Transfer from savings to digital wallet
transfer - Transfer between digital wallets

Key Fields: key (UUID), ref_number, sender_acct_key, receiver_acct_key, type, amount, status

wlt_transactions_details
Use: Double-entry accounting ledger entries
Purpose: Store individual debit and credit entries for each transaction
Contains: Account-specific transaction details with DR/CR entries
Constraint: Each entry must be either debit OR credit (not both)
Key Fields: key (UUID), trxn_key, acct_key, entry, amount_dr, amount_cr


4. Transaction Flow Examples
Deposit Operation (External → Savings)
Withdrawal Operation (Savings → External)
Top-Up Operation (Savings → Wallet)
Transfer Operation (Wallet → Wallet)

5. Seeded Data (via php artisan db:seed)
Default Admin User
Email: root@miniwallet.com
Password: masterpass
Role: Admin/Root user

Initial System Accounts
Created automatically for each user
Type: initial
Purpose: Handle external money flows for deposits/withdrawals
Visibility: Hidden from users (system-only)

Bank Master Data
Pre-populated list of supported banks
Includes major banks with SWIFT codes and currencies
Used for creating savings accounts

6. Data Integrity Features
Audit Trail
All tables include: created_by, updated_by, deleted_by, created_at, updated_at, deleted_at

Tracks who made changes and when
Soft Deletes

Records are marked as deleted, not physically removed
Maintains transaction history and audit trail

Optimistic Locking
version fields prevent concurrent update conflicts
Incremented on each record update

Balance Validation
Savings accounts: Minimum balance requirements (AED 1,000)
Double-entry: Debits always equal credits
Transaction atomicity: All-or-nothing updates