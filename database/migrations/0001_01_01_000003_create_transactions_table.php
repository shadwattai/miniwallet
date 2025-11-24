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
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->unique();

            $table->string('ref_number', 50)->unique();  
            $table->uuid('sender_acct_key')->notNullable();
            $table->uuid('receiver_acct_key')->notNullable();
            $table->text('description')->nullable(); 
           
            $table->string('type', 20); // deposit, withdrawal, transfer
            $table->decimal('amount', 26, 2);
            $table->decimal('commission_fee', 26, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending, completed, failed, cancelled
             
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps(); 


            $table->foreign('sender_acct_key')->references('key')->on('wlt_accounts')->onDelete('cascade');
            $table->foreign('receiver_acct_key')->references('key')->on('wlt_accounts')->onDelete('restrict');
        });

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