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
        Schema::create('wlt_banks', function (Blueprint $table) {
             $table->bigIncrements('id');
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->primary();

            $table->string('bank_name');
            $table->string('bank_code', 10)->unique();
            $table->string('bank_logo', 255)->nullable();
            $table->string('swift_code', 11)->nullable();
            $table->string('country_code', 3)->default('UAE');
            $table->string('bank_type', 20)->default('commercial'); // commercial, islamic, central, investment
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_transfers')->default(true);
            $table->boolean('supports_deposits')->default(true);
            $table->boolean('supports_withdrawals')->default(true);
            $table->decimal('min_balance', 26, 2)->default(0);
            $table->decimal('max_balance', 26, 2)->nullable();
            $table->decimal('daily_transfer_limit', 26, 2)->nullable();
            $table->json('supported_currencies')->nullable(); // JSON array of supported currencies
            $table->text('notes')->nullable();
            
            // Audit fields with proper constraints
            $table->integer('version')->default(1);
            $table->uuid('created_by');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('deleted_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            
        });

        // Add check constraint for updated_at >= created_at
        DB::statement('ALTER TABLE wlt_banks ADD CONSTRAINT chk_wlt_banks_updated_at CHECK (updated_at >= created_at)');

        // Indexes
        DB::statement('CREATE INDEX "idx_wlt_banks_code" ON wlt_banks USING BTREE(bank_code, is_active)');
        DB::statement('CREATE INDEX "idx_wlt_banks_swift" ON wlt_banks USING BTREE(swift_code) WHERE swift_code IS NOT NULL');
        DB::statement('CREATE INDEX "idx_wlt_banks_country" ON wlt_banks USING BTREE(country_code, bank_type)');
        
        // Trigger
        DB::statement('CREATE TRIGGER set_timestamp_wlt_banks BEFORE UPDATE ON wlt_banks FOR EACH ROW EXECUTE PROCEDURE trigger_update_timestamp()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wlt_banks');
    }
};