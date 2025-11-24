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
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->unique();

            $table->uuid('user_key')->notNullable();
            $table->string('account_number', 20)->unique();
            $table->string('account_name');
            $table->string('account_type', 20)->default('wallet'); // wallet, savings, checking
            $table->string('currency', 3)->default('AED');
            $table->decimal('balance', 26, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('user_key')->references('key')->on('users')->onDelete('restrict');
        });

        // Indexes
        DB::statement('CREATE INDEX "idx_wlt_accounts_user" ON wlt_accounts USING BTREE(user_key, is_active)');
        DB::statement('CREATE INDEX "idx_wlt_accounts_number" ON wlt_accounts USING BTREE(account_number)');
        
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