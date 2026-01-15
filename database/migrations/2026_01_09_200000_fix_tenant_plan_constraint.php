<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * SQLite doesn't support ALTER COLUMN to change CHECK constraints.
     * We need to recreate the table to update the plan enum values.
     */
    public function up(): void
    {
        // For SQLite: We need to recreate the table without the CHECK constraint on 'plan'
        // or update it to accept new values. Using raw SQL is the simplest approach.

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite approach: Disable foreign keys, recreate table
            DB::statement('PRAGMA foreign_keys=OFF');

            // Create a new table with the updated plan constraint
            DB::statement('
                CREATE TABLE tenants_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    is_trial BOOLEAN NOT NULL DEFAULT 1,
                    trial_ends_at DATETIME NULL,
                    is_active BOOLEAN NOT NULL DEFAULT 1,
                    plan VARCHAR(50) NOT NULL DEFAULT \'basic\',
                    max_users INTEGER NOT NULL DEFAULT 1,
                    max_customers INTEGER NOT NULL DEFAULT 100,
                    max_vehicles INTEGER NOT NULL DEFAULT 500,
                    max_work_orders INTEGER NULL,
                    slug VARCHAR(255) NULL,
                    subdomain VARCHAR(255) NULL,
                    api_key VARCHAR(255) NULL,
                    subscription_ends_at DATETIME NULL,
                    features TEXT NULL,
                    settings TEXT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                )
            ');

            // Copy existing data
            DB::statement('
                INSERT INTO tenants_new (id, name, email, is_trial, trial_ends_at, is_active, plan, max_users, max_customers, max_vehicles, max_work_orders, slug, subdomain, api_key, subscription_ends_at, features, settings, created_at, updated_at)
                SELECT id, name, email, is_trial, trial_ends_at, is_active, 
                    CASE plan 
                        WHEN \'free\' THEN \'basic\'
                        WHEN \'premium\' THEN \'standard\'
                        WHEN \'enterprise\' THEN \'custom\'
                        ELSE plan 
                    END,
                    max_users, max_customers, max_vehicles, max_work_orders, slug, subdomain, api_key, subscription_ends_at, features, settings, created_at, updated_at
                FROM tenants
            ');

            // Drop old table and rename new one
            DB::statement('DROP TABLE tenants');
            DB::statement('ALTER TABLE tenants_new RENAME TO tenants');

            // Re-enable foreign keys
            DB::statement('PRAGMA foreign_keys=ON');
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            // PostgreSQL approach: Use ALTER COLUMN with TYPE and USING
            DB::statement("ALTER TABLE tenants ALTER COLUMN plan TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE tenants ALTER COLUMN plan SET DEFAULT 'basic'");
            // Update old plan values to new ones
            DB::statement("UPDATE tenants SET plan = 'basic' WHERE plan = 'free'");
            DB::statement("UPDATE tenants SET plan = 'standard' WHERE plan = 'premium'");
            DB::statement("UPDATE tenants SET plan = 'custom' WHERE plan = 'enterprise'");
        } else {
            // For MySQL, just update the enum
            DB::statement("ALTER TABLE tenants MODIFY COLUMN plan ENUM('basic', 'standard', 'custom') DEFAULT 'basic'");
        }

        // Set max_work_orders for basic plans
        DB::table('tenants')->where('plan', 'basic')->whereNull('max_work_orders')->update(['max_work_orders' => 50]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No practical way to reverse this cleanly
    }
};
