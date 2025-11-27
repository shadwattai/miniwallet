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
        Schema::create('wlt_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->primary();

            $table->string('ref_number', 50)->unique();  
            $table->uuid('sender_acct_key');
            $table->uuid('receiver_acct_key');
            $table->text('description')->nullable(); 
           
            $table->string('type', 20); // deposit, topup, withdrawal, transfer
            $table->decimal('amount', 26, 2);
            $table->decimal('commission_fee', 26, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending, completed, failed, cancelled
             
            // Audit fields with proper constraints
            $table->integer('version')->default(1);
            $table->uuid('created_by');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('deleted_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
             

            $table->foreign('sender_acct_key')->references('key')->on('wlt_accounts')->onDelete('cascade');
            $table->foreign('receiver_acct_key')->references('key')->on('wlt_accounts')->onDelete('restrict');
        });

        // Add check constraint for updated_at >= created_at
        DB::statement('ALTER TABLE wlt_transactions ADD CONSTRAINT chk_wlt_transactions_updated_at CHECK (updated_at >= created_at)');

        // Indexes
        DB::statement('CREATE INDEX "idx_wlt_transactions_type_status" ON wlt_transactions USING BTREE(type, status, created_at)');
        DB::statement('CREATE INDEX "idx_wlt_transactions_user" ON wlt_transactions USING BTREE(updated_by, created_at)');
        DB::statement('CREATE INDEX "idx_wlt_transactions_reference" ON wlt_transactions USING BTREE(ref_number)');
        
        // Trigger
        DB::statement('CREATE TRIGGER set_timestamp_wlt_transactions BEFORE UPDATE ON wlt_transactions FOR EACH ROW EXECUTE PROCEDURE trigger_update_timestamp()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wlt_transactions');
    }
};