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
PHP 8.4
Laravel 12.32
PostgreSQL 17
Vue 3 + Composition API
PrimeVue CSS component library

## File structure
Follows oppinionated structure from the Laravel starter kit

## Artifacts API
I created a comprehensive REST-ish API with sanitized reusable methods for common CRUD operations
Controller classes are found in the Controllers/Artifacts directory and are callable globally in the system.

## Database structure


## Operations

1. When User logs in
- Going to My Wallets, they can create
(i) a savings account mapped to the bank
(ii) a digital wallet account that can transfer to other wallets

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

