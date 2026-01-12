<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the missing last_activity_at column to the tenants table.
     * This was also accidentally dropped during the table recreation in the earlier migration.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Check if column exists first to avoid errors if run multiple times
            $hasColumn = DB::select('PRAGMA table_info(tenants)');
            $columnExists = false;
            foreach ($hasColumn as $col) {
                if ($col->name === 'last_activity_at') {
                    $columnExists = true;
                    break;
                }
            }

            if (! $columnExists) {
                Schema::table('tenants', function (Blueprint $table) {
                    $table->timestamp('last_activity_at')->nullable();
                });
            }
        } else {
            // For MySQL/Postgres etc
            if (! Schema::hasColumn('tenants', 'last_activity_at')) {
                Schema::table('tenants', function (Blueprint $table) {
                    $table->timestamp('last_activity_at')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });
    }
};
