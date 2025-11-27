# Miniwallet System Documentation

## Overview
Miniwallet is a web-based financial application designed to manage digital wallets and savings accounts using a **double-entry accounting system**. It provides users with a secure and efficient platform for managing their finances, including deposits, withdrawals, top-ups, and transfers. The system ensures data integrity, accountability, and transparency through features like audit trails, soft deletes, and balance validation.

---

## Key Features

### 1. **User Management**
- **Registration and Authentication**: Users can sign up and log in securely.
- **Role-Based Access Control**: Supports roles such as Admin and User.
- **Invisible System Accounts**: Automatically created for each user to handle internal operations like deposits and withdrawals.

### 2. **Account Management**
- **Savings Accounts**: Linked to banks for deposits and withdrawals.
- **Digital Wallets**: Used for peer-to-peer transfers.
- **Invisible Accounts**: System-managed accounts for internal operations, hidden from users.

### 3. **Transaction Management**
- **Deposit**: Add funds to savings accounts.
- **Withdrawal**: Remove funds from savings accounts.
- **Top-Up**: Transfer funds from savings accounts to digital wallets.
- **Transfer**: Move funds between wallets of the same currency.
- **Commission Fees**: A 1.5% fee is applied to transfers, credited to the root admin's account.

### 4. **Dashboard and Statistics**
- **Transaction Statistics**: View transaction counts and total amounts by type.
- **Recent Transactions**: Display the last 5 transactions for quick reference.
- **Account Balances**: View the total balance of all wallet accounts.

### 5. **Data Integrity**
- **Double-Entry Accounting**: Ensures debits always equal credits.
- **Balance Validation**: Enforces minimum balance requirements for savings accounts.
- **Atomic Transactions**: All-or-nothing updates to maintain consistency.

### 6. **Audit Trail**
- Tracks all changes made to the system, including who made the changes and when.
- Provides a detailed history of transactions for accountability.

### 7. **WebSocket Integration**
- Real-time updates for wallet balances and notifications.
- Supports browser notifications for important events.

---

## Tech Stack

### Backend
- **Language**: PHP 8.4
- **Framework**: Laravel 12.32
- **Database**: PostgreSQL 17
- **WebSocket**: Pusher for real-time updates

### Frontend
- **Framework**: Vue 3 (Composition API)
- **Routing**: InertiaJS
- **UI Components**: PrimeVue CSS library

---

## Installation Guide

### Prerequisites
- PHP 8.4 or higher
- Composer
- Node.js and npm
- PostgreSQL 17

### Steps
1. **Clone the Repository**:
   ```bash
   git clone <repository-url>
   cd miniwallet
   ```

2. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

3. **Install JavaScript Dependencies**:
   ```bash
   npm install
   ```

4. **Build Frontend Assets**:
   ```bash
   npm run dev
   ```

5. **Set Up the Database**:
   - Create a PostgreSQL database named `miniwallet`.
   - Run migrations:
     ```bash
     php artisan migrate
     ```
   - Seed the database:
     ```bash
     php artisan db:seed
     ```

6. **Start the Development Server**:
   ```bash
   php artisan serve
   ```

7. **Access the Application**:
   Open your browser and navigate to `http://localhost:8000`.

---

## Database Structure

### Core Tables
1. **`users`**: Stores user accounts and authentication details.
2. **`wlt_accounts`**: Manages savings, wallet, and invisible accounts.
3. **`wlt_banks`**: Contains a list of supported banks.
4. **`wlt_transactions`**: Logs all high-level transaction details.
5. **`wlt_transactions_details`**: Stores double-entry accounting ledger entries.

### Relationships
- Each user can have multiple accounts.
- Transactions link sender and receiver accounts.

---

## API Endpoints

### Authentication
- `POST /login`: Authenticate a user.
- `POST /logout`: Log out a user.

### Accounts
- `GET /accounts`: List all accounts for the authenticated user.
- `POST /accounts`: Create a new account.

### Transactions
- `GET /transactions`: List recent transactions.
- `POST /transactions/deposit`: Make a deposit.
- `POST /transactions/withdraw`: Make a withdrawal.
- `POST /transactions/topup`: Top up a wallet.
- `POST /transactions/transfer`: Transfer funds between wallets.

---

## Operations

### 1. **User Signup**
- Creates a savings account, wallet account, and an invisible system account.

### 2. **Deposit**
- Credits the invisible account and debits the savings account.

### 3. **Withdrawal**
- Debits the invisible account and credits the savings account.

### 4. **Top-Up**
- Transfers funds from the savings account to the wallet account.

### 5. **Transfer**
- Moves funds between wallets of the same currency.
- Deducts a 1.5% commission fee.

---

## Security Features

- **Authentication**: Laravel's built-in authentication with hashed passwords.
- **Authorization**: Role-based access control for sensitive operations.
- **Data Validation**: Ensures all inputs meet the required format.
- **CSRF Protection**: Prevents cross-site request forgery attacks.
- **Audit Trail**: Tracks all changes for accountability.

---

## WebSocket Integration

- **Real-Time Updates**: Wallet balances and notifications are updated in real-time.
- **Browser Notifications**: Alerts users of important events.
- **Configuration**:
  - WebSocket credentials are defined in the .env file.
  - Example:
    ```env
    PUSHER_APP_KEY=your-pusher-key
    PUSHER_APP_CLUSTER=your-cluster
    ```

---

## Troubleshooting

### Common Issues
1. **Database Connection Error**:
   - Ensure the .env file has the correct database credentials.

2. **Assets Not Loading**:
   - Run `npm run dev` to rebuild frontend assets.

3. **WebSocket Not Connecting**:
   - Verify Pusher credentials in the .env file.

### Logs
- Application logs: laravel.log
- WebSocket logs: Check the browser console for errors.

---

## Future Enhancements

- Add multi-currency support for transactions.
- Implement advanced reporting and analytics.
- Introduce mobile app integration.
- Enhance the UI with more interactive components.

---

For more details, refer to the README.md or contact the development team.