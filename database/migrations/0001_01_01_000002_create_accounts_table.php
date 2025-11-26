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
        Schema::create('wlt_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->primary();

            $table->uuid('user_key');
            $table->uuid('bank_key');
            $table->string('account_number', 20)->unique();
            $table->string('account_name');
            $table->string('account_type', 20)->default('wallet'); // wallet, savings, checking, initial
            $table->string('currency', 3)->default('AED');
            $table->decimal('balance', 26, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            
            // Audit fields with proper constraints
            $table->integer('version')->default(1);
            $table->uuid('created_by');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('deleted_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            
            
            $table->foreign('user_key')->references('key')->on('users')->onDelete('restrict');
            $table->foreign('bank_key')->references('key')->on('wlt_banks')->onDelete('restrict');
        });

        // Add check constraint for updated_at >= created_at
        DB::statement('ALTER TABLE wlt_accounts ADD CONSTRAINT chk_wlt_accounts_updated_at CHECK (updated_at >= created_at)');

        // Indexes
        DB::statement('CREATE INDEX "idx_wlt_accounts_user" ON wlt_accounts USING BTREE(user_key, is_active)');
        DB::statement('CREATE INDEX "idx_wlt_accounts_number" ON wlt_accounts USING BTREE(account_number, bank_key)');
        
        // Trigger
        DB::statement('CREATE TRIGGER set_timestamp_wlt_accounts BEFORE UPDATE ON wlt_accounts FOR EACH ROW EXECUTE PROCEDURE trigger_update_timestamp()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wlt_accounts');
    }
};