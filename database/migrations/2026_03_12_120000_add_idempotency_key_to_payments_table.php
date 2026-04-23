<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('transaction_reference');
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX payments_invoice_id_idempotency_key_unique ON payments (invoice_id, idempotency_key) WHERE idempotency_key IS NOT NULL');

            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['invoice_id', 'idempotency_key'], 'payments_invoice_id_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS payments_invoice_id_idempotency_key_unique');
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique('payments_invoice_id_idempotency_key_unique');
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
