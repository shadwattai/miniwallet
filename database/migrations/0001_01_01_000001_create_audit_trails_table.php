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
        // Create audit_action enum type first
        DB::statement("CREATE TYPE audit_action AS ENUM ('create', 'read', 'update', 'delete', 'login', 'logout', 'approve', 'reject', 'export')");

        Schema::create('users_audit_trails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('key')->default(DB::raw('uuid_generate_v4()'))->primary();

            // Audit details (action column will be added after table creation)
            $table->string('description')->nullable();
            $table->string('table_name', 800)->nullable();
            $table->jsonb('prev_data')->nullable();
            $table->jsonb('new_data')->nullable();
            $table->timestampTz('action_time')->default(DB::raw('CURRENT_TIMESTAMP'));
            
            // User session details
            $table->string('user_ip')->nullable();
            $table->string('user_os')->nullable();
            $table->string('user_browser')->nullable();
            $table->string('user_device')->nullable(); 

            // System columns
            $table->integer('version')->default(1);
            $table->uuid('created_by');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('deleted_by')->nullable();
            $table->timestampTz('deleted_at')->nullable(); 


        });

        // Add the action column with custom enum type after table creation
        DB::statement('ALTER TABLE users_audit_trails ADD COLUMN action audit_action NOT NULL');

        // Add check constraint for updated_at >= created_at
        DB::statement('ALTER TABLE users_audit_trails ADD CONSTRAINT chk_users_audit_trails_updated_at CHECK (updated_at >= created_at)');

        // Indexes for performance
        DB::statement('CREATE INDEX "idx_users_audit_trails" ON users_audit_trails USING BTREE(acc_key, bns_key, usr_key, app_key)');
        DB::statement('CREATE INDEX "idx_users_audit_trails_action" ON users_audit_trails USING BTREE(action, action_time DESC)');
        DB::statement('CREATE INDEX "idx_users_audit_trails_table" ON users_audit_trails USING BTREE(table_name, action_time DESC)');
        DB::statement('CREATE INDEX "idx_users_audit_trails_user" ON users_audit_trails USING BTREE(usr_key, action_time DESC)');
        
        // Trigger
        DB::statement('CREATE TRIGGER set_timestamp BEFORE UPDATE ON users_audit_trails FOR EACH ROW EXECUTE PROCEDURE trigger_update_timestamp()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_audit_trails');
        
        // Drop the enum type
        DB::statement('DROP TYPE IF EXISTS audit_action');
    }
};