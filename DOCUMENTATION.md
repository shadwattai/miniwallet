```markdown
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

## Evaluation Criteria

### 1. **Scalable Balance Management**
- **Approach**: Balances are calculated and updated using database transactions to ensure atomicity and consistency. The system leverages **PostgreSQL's transactional capabilities** to handle high traffic and millions of transaction records efficiently.

- **Optimization**: Indexes are applied to frequently queried fields (e.g., `user_key`, `account_type`) to improve query performance.

- **Concurrency Handling**: Optimistic locking is implemented to prevent race conditions during balance updates. This ensures that no two processes can update the same balance simultaneously.

- **Batch Processing**: Laravel's queue system is used for processing large volumes of transactions asynchronously, ensuring the system remains responsive under heavy load.

### 2. **Correct Real-Time Integration**
- **Event Broadcasting**: Pusher is used to broadcast events such as balance updates and new transactions. These events are triggered in the backend whenever a transaction is processed.

- **Frontend Handling**: The Vue 3 frontend listens for these events and updates the UI instantly without requiring a page refresh. For example:

  - Wallet balances are updated in real-time.
  - Recent transactions are appended dynamically to the transaction list.
- **Scalability**: Pusher's clustering capabilities ensure that real-time updates remain performant under high traffic.

### 3. **Code Quality**
- **Standards**: The code adheres to **PSR-12** coding standards for PHP, ensuring consistency and readability.
- **Structure**: The project follows Laravel's opinionated structure, separating concerns into controllers, models, and views.
- **Readability**: Code is well-documented with comments explaining critical logic.
- **Maintainability**: Reusable methods are implemented for common operations (e.g., `getAccountBalance`, `getTransactionStatistics`).

### 4. **Problem-Solving**
- **Concurrency Challenges**: High concurrency is addressed using database-level locks and Laravel's queue system to process transactions asynchronously.
- **Data Integrity**: Double-entry accounting ensures that every transaction has corresponding debit and credit entries, maintaining financial accuracy.
- **Error Handling**: Comprehensive error handling is implemented to catch and log exceptions, ensuring the system remains stable under unexpected conditions.

### 5. **Security**
- **Validation**: All user inputs are validated using Laravel's built-in validation rules to prevent invalid data from being processed.
- **Authorization**: Role-based access control ensures that only authorized users can perform sensitive operations.
- **CSRF Protection**: Cross-Site Request Forgery protection is enabled for all forms.
- **Password Hashing**: User passwords are hashed using Laravel's `bcrypt` algorithm.
- **Audit Trail**: Tracks all changes to critical data, providing accountability and traceability.

### 6. **Git Usage**
- **Commit History**: The repository maintains a clean and understandable commit history. Each commit message is meaningful and follows the convention:
  - `feat`: For new features.
  - `fix`: For bug fixes.
  - `refactor`: For code improvements.
  - `docs`: For documentation updates.
- **Branching Strategy**: Feature branches are used for development, and pull requests are reviewed before merging into the main branch.

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
   Open your browser and  tonavigate `http://localhost:8000`.

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

## Balance Calculation Techniques

### 1. **Pre-Stored Balances**
- **Description**: Balances are stored directly in the `wlt_accounts` table and updated during every transaction. This ensures fast reads for real-time balance display.
- **Use Case**: Ideal for high-traffic systems where performance is critical.
- **Advantages**:
  - Fast and efficient for real-time balance queries.
  - Reduces computational overhead during balance retrieval.
- **Disadvantages**:
  - Risk of discrepancies if updates fail or are not atomic.
  - Requires reconciliation to ensure data integrity.

### 2. **Dynamic Balance Calculation**
- **Description**: Balances are calculated dynamically by summing up all debit and credit entries in the `wlt_transactions_details` table for a specific account.
- **Use Case**: Suitable for auditing, reconciliation, and historical reporting.
- **Advantages**:
  - Ensures data integrity by deriving balances directly from transaction history.
  - Fully traceable and auditable.
- **Disadvantages**:
  - Computationally expensive for accounts with a large number of transactions.
  - Slower performance for real-time balance queries.

**Example Query**:
```sql
SELECT 
    SUM(amount_cr) - SUM(amount_dr) AS balance
FROM 
    wlt_transactions_details
WHERE 
    acct_key = :accountKey;
```

### 3. **Hybrid Approach**
- **Description**: Combines pre-stored balances with dynamic calculations. Balances are stored in the `wlt_accounts` table for real-time performance, while dynamic calculations are used for reconciliation and auditing.
- **Use Case**: Ideal for systems requiring both performance and resiliency.
- **Advantages**:
  - Provides fast reads for real-time use cases.
  - Ensures resiliency by allowing balances to be reconstructed dynamically.
- **Disadvantages**:
  - Requires periodic reconciliation to ensure consistency between stored and calculated balances.

### 4. **Balance Snapshots**
- **Description**: Periodic snapshots of account balances are stored in a separate table (e.g., `wlt_account_snapshots`). These snapshots are used as a starting point for dynamic calculations.
- **Use Case**: Suitable for historical reporting and systems with high transaction volumes.
- **Advantages**:
  - Reduces the computational overhead of dynamic calculations.
  - Efficient for querying balances at specific points in time.
- **Disadvantages**:
  - Requires additional storage for snapshots.
  - Snapshots must be maintained and updated periodically.

**Snapshot Table Example**:
```sql
CREATE TABLE wlt_account_snapshots (
    acct_key UUID,
    balance DECIMAL(26, 8),
    snapshot_date DATE,
    PRIMARY KEY (acct_key, snapshot_date)
);
```

### 5. **Reconciliation Process**
- **Description**: Periodically compare pre-stored balances with dynamically calculated balances to detect and fix discrepancies.
- **Use Case**: Ensures data integrity in systems using pre-stored balances.
- **Advantages**:
  - Identifies and resolves inconsistencies.
  - Improves system reliability.
- **Disadvantages**:
  - Requires additional processing time for reconciliation.

**Reconciliation Example**:
```php
public function reconcileBalances(): void
{
    $accounts = DB::table('wlt_accounts')->get();

    foreach ($accounts as $account) {
        $dynamicBalance = $this->calculateDynamicBalance($account->key);

        if (abs($account->balance - $dynamicBalance) > 0.01) { // Allow small rounding differences
            Log::warning('Balance discrepancy detected', [
                'account_key' => $account->key,
                'stored_balance' => $account->balance,
                'calculated_balance' => $dynamicBalance,
            ]);

            // Optionally, update the stored balance to match the calculated balance
            DB::table('wlt_accounts')
                ->where('key', $account->key)
                ->update(['balance' => $dynamicBalance]);
        }
    }
}
```
````
