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
     * Adds the missing deleted_at column to the tenants table.
     * This was accidentally dropped during the table recreation in the previous migration.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Check if column exists first to avoid errors if run multiple times
            $hasColumn = DB::select('PRAGMA table_info(tenants)');
            $columnExists = false;
            foreach ($hasColumn as $col) {
                if ($col->name === 'deleted_at') {
                    $columnExists = true;
                    break;
                }
            }

            if (! $columnExists) {
                Schema::table('tenants', function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        } else {
            // For MySQL/Postgres etc
            if (! Schema::hasColumn('tenants', 'deleted_at')) {
                Schema::table('tenants', function (Blueprint $table) {
                    $table->softDeletes();
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
            $table->dropSoftDeletes();
        });
    }
};
