<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wlt_accounts_balances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->primary();

            $table->uuid('acct_key');
            $table->uuid('trxn_key');
            $table->decimal('prev_balance', 26, 2);
            $table->decimal('trxn_amount', 26, 2); // positive for credit, negative for debit
            $table->decimal('running_balance', 26, 2);
            
            // Audit fields with proper constraints
            $table->integer('version')->default(1);
            $table->uuid('created_by');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('deleted_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            
            
            $table->foreign('acct_key')->references('key')->on('wlt_accounts')->onDelete('restrict');
            $table->foreign('trxn_key')->references('key')->on('wlt_transactions')->onDelete('restrict');
        });

        // Add check constraint for updated_at >= created_at
        DB::statement('ALTER TABLE wlt_accounts_balances ADD CONSTRAINT chk_wlt_accounts_balances_updated_at CHECK (updated_at >= created_at)');

        // Critical indexes for performance
        DB::statement('CREATE INDEX "idx_balance_history_account" ON wlt_accounts_balances USING BTREE(acct_key, created_at DESC)');
        DB::statement('CREATE INDEX "idx_balance_history_transaction" ON wlt_accounts_balances USING BTREE(trxn_key)');
        
        // Unique constraint to prevent duplicate entries
        DB::statement('CREATE UNIQUE INDEX "idx_balance_history_unique" ON wlt_accounts_balances(acct_key, trxn_key)');
        
        // Trigger
        DB::statement('CREATE TRIGGER set_timestamp_wlt_accounts_balances BEFORE UPDATE ON wlt_accounts_balances FOR EACH ROW EXECUTE PROCEDURE trigger_update_timestamp()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wlt_accounts_balances');
    }
};