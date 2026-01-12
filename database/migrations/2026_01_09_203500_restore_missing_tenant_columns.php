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
     * Adds ALL missing columns to the tenants table that were lost during the table recreation.
     * This ensures the schema matches the Tenant model fillables.
     */
    public function up(): void
    {
        $columns = [
            'phone' => ['type' => 'string', 'nullable' => true, 'after' => 'email'],
            'address' => ['type' => 'text', 'nullable' => true, 'after' => 'phone'],
            'city' => ['type' => 'string', 'nullable' => true, 'after' => 'address'],
            'country' => ['type' => 'string', 'nullable' => true, 'after' => 'city'],
            'logo_url' => ['type' => 'string', 'nullable' => true, 'after' => 'settings'],
            'primary_color' => ['type' => 'string', 'nullable' => true, 'after' => 'logo_url'],
            'secondary_color' => ['type' => 'string', 'nullable' => true, 'after' => 'primary_color'],
            'database_name' => ['type' => 'string', 'nullable' => true, 'after' => 'secondary_color'],
            'timezone' => ['type' => 'string', 'default' => 'UTC', 'nullable' => true, 'after' => 'database_name'],
            'locale' => ['type' => 'string', 'default' => 'en', 'nullable' => true, 'after' => 'timezone'],
            'currency' => ['type' => 'string', 'default' => 'EUR', 'nullable' => true, 'after' => 'locale'],
            'domain' => ['type' => 'string', 'nullable' => true, 'unique' => true, 'after' => 'slug'],
            'two_factor_required' => ['type' => 'boolean', 'default' => false, 'after' => 'currency'],
            'ip_whitelist' => ['type' => 'text', 'nullable' => true, 'after' => 'two_factor_required'],
            'audit_logs_enabled' => ['type' => 'boolean', 'default' => false, 'after' => 'ip_whitelist'],
            'api_rate_limit' => ['type' => 'integer', 'default' => 60, 'after' => 'api_key'],
            'integrations' => ['type' => 'text', 'nullable' => true, 'after' => 'features'],
        ];

        if (DB::connection()->getDriverName() === 'sqlite') {
            $existingColumnsRaw = DB::select('PRAGMA table_info(tenants)');
            $existingColumns = [];
            foreach ($existingColumnsRaw as $col) {
                $existingColumns[] = $col->name;
            }

            Schema::table('tenants', function (Blueprint $table) use ($columns, $existingColumns) {
                foreach ($columns as $name => $def) {
                    if (! in_array($name, $existingColumns)) {
                        $type = $def['type'];
                        $col = $table->$type($name);

                        if ($def['nullable'] ?? false) {
                            $col->nullable();
                        }

                        if (isset($def['default'])) {
                            $col->default($def['default']);
                        }
                    }
                }
            });
        } else {
            Schema::table('tenants', function (Blueprint $table) use ($columns) {
                foreach ($columns as $name => $def) {
                    if (! Schema::hasColumn('tenants', $name)) {
                        $type = $def['type'];
                        $col = $table->$type($name);

                        if ($def['nullable'] ?? false) {
                            $col->nullable();
                        }

                        if (isset($def['default'])) {
                            $col->default($def['default']);
                        }
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No practical way to reverse adding multiple optional columns cleanly
    }
};
