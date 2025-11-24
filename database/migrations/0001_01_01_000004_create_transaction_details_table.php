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
        Schema::create('wlt_transactions_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->unique();

            $table->uuid('trxn_key')->notNullable();
            $table->uuid('acct_key')->notNullable();
            $table->string('description')->nullable(); 

            $table->string('entry', 2); // 'DR' for debit, 'CR' for credit
            $table->decimal('amount_dr', 26, 2)->default(0);
            $table->decimal('amount_cr', 26, 2)->default(0);
            

            // Audit fields with proper constraints
            $table->integer('version')->default(1);
            $table->uuid('created_by');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('deleted_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            
            
            $table->foreign('trxn_key')->references('key')->on('wlt_transactions')->onDelete('cascade');
            $table->foreign('acct_key')->references('key')->on('wlt_accounts')->onDelete('restrict');
        });

        // Indexes
        DB::statement('CREATE INDEX "idx_wlt_transactions_details_trxn" ON wlt_transactions_details USING BTREE(trxn_key)');
        DB::statement('CREATE INDEX "idx_wlt_transactions_details_account" ON wlt_transactions_details USING BTREE(acct_key, created_at)');
        DB::statement('CREATE INDEX "idx_wlt_transactions_details_entry" ON wlt_transactions_details USING BTREE(entry)');
        
        // Constraint to ensure either DR or CR is filled, not both
        DB::statement('ALTER TABLE wlt_transactions_details ADD CONSTRAINT chk_wlt_transactions_details_amount CHECK ((amount_dr > 0 AND amount_cr = 0) OR (amount_cr > 0 AND amount_dr = 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wlt_transactions_details');
    }
};