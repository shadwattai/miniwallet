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
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->unique();

            $table->uuid('acct_key');
            $table->uuid('trxn_key');
            $table->decimal('prev_balance', 26, 2);
            $table->decimal('trxn_amount', 26, 2); // positive for credit, negative for debit
            $table->decimal('running_balance', 26, 2);
            $table->timestamps();
            
            $table->foreign('acct_key')->references('key')->on('wlt_accounts')->onDelete('restrict');
            $table->foreign('trxn_key')->references('key')->on('wlt_transactions')->onDelete('restrict');
        });

        // Critical indexes for performance
        DB::statement('CREATE INDEX "idx_balance_history_account" ON wlt_accounts_balances USING BTREE(acct_key, created_at DESC)');
        DB::statement('CREATE INDEX "idx_balance_history_transaction" ON wlt_accounts_balances USING BTREE(trxn_key)');
        
        // Unique constraint to prevent duplicate entries
        DB::statement('CREATE UNIQUE INDEX "idx_balance_history_unique" ON wlt_accounts_balances(acct_key, trxn_key)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wlt_accounts_balances');
    }
};