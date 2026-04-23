<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-04: catch hot-path query columns that aren't yet indexed.
 *
 * `foreignId()->constrained()` adds the FK constraint but does NOT create
 * an index on the FK column in PostgreSQL. Most column access is through
 * tenant_id composite indexes we already have, but a few scheduling /
 * reporting queries walk the raw FK and benefit from dedicated indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Work orders: scheduling dashboard filters by technician + status
        // and by scheduled_at (B-03 conflict checks scan this).
        Schema::table('work_orders', function (Blueprint $table) {
            if (! $this->hasIndex('work_orders', 'work_orders_technician_id_status_index')) {
                $table->index(['technician_id', 'status']);
            }
            if (! $this->hasIndex('work_orders', 'work_orders_scheduled_at_index')) {
                $table->index('scheduled_at');
            }
            if (! $this->hasIndex('work_orders', 'work_orders_tenant_id_scheduled_at_index')) {
                $table->index(['tenant_id', 'scheduled_at']);
            }
        });

        // Appointments: calendar view queries scheduled_at within a tenant.
        Schema::table('appointments', function (Blueprint $table) {
            if (! $this->hasIndex('appointments', 'appointments_tenant_id_start_time_index')) {
                $table->index(['tenant_id', 'start_time']);
            }
        });

        // Payments: lookup by invoice (status sync, receipt display).
        Schema::table('payments', function (Blueprint $table) {
            if (! $this->hasIndex('payments', 'payments_invoice_id_index')) {
                $table->index('invoice_id');
            }
        });

        // Invoice items / quote items: we just added tenant_id; make sure
        // the other side (parent FK) is covered too. Invoice parent index
        // was added by the earlier performance migration — quote parent is not.
        Schema::table('quote_items', function (Blueprint $table) {
            if (! $this->hasIndex('quote_items', 'quote_items_quote_id_index')) {
                $table->index('quote_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if ($this->hasIndex('work_orders', 'work_orders_technician_id_status_index')) {
                $table->dropIndex(['technician_id', 'status']);
            }
            if ($this->hasIndex('work_orders', 'work_orders_scheduled_at_index')) {
                $table->dropIndex(['scheduled_at']);
            }
            if ($this->hasIndex('work_orders', 'work_orders_tenant_id_scheduled_at_index')) {
                $table->dropIndex(['tenant_id', 'scheduled_at']);
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if ($this->hasIndex('appointments', 'appointments_tenant_id_start_time_index')) {
                $table->dropIndex(['tenant_id', 'start_time']);
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if ($this->hasIndex('payments', 'payments_invoice_id_index')) {
                $table->dropIndex(['invoice_id']);
            }
        });

        Schema::table('quote_items', function (Blueprint $table) {
            if ($this->hasIndex('quote_items', 'quote_items_quote_id_index')) {
                $table->dropIndex(['quote_id']);
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
