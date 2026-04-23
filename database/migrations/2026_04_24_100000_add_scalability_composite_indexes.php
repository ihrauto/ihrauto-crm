<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint A-1 (Scalability 200-tenants review, BL-2) — add the composite
 * indexes that the dashboard and finance hot queries need. Without these
 * the app walks 400k-row invoice tables on every dashboard load.
 *
 * Use `hasIndex()` gates so this migration is idempotent across the
 * staging / production / local databases which may already have subsets
 * of these indexes from earlier performance migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! $this->hasIndex('invoices', 'invoices_tenant_status_due_idx')) {
                // Covers: overdue counts, unpaid totals, the finance UNPAID tab.
                $table->index(['tenant_id', 'status', 'due_date'], 'invoices_tenant_status_due_idx');
            }
            if (! $this->hasIndex('invoices', 'invoices_tenant_issue_date_idx')) {
                // Covers: revenue-by-year, year-of-year reporting.
                $table->index(['tenant_id', 'issue_date'], 'invoices_tenant_issue_date_idx');
            }
            if (! $this->hasIndex('invoices', 'invoices_tenant_status_idx')) {
                // Covers: unpaid_total/paid counts — lighter index useful when
                // due_date is NULL (drafts).
                $table->index(['tenant_id', 'status'], 'invoices_tenant_status_idx');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! $this->hasIndex('customers', 'customers_tenant_created_at_idx')) {
                // Covers: monthly customer-growth metrics on the dashboard.
                $table->index(['tenant_id', 'created_at'], 'customers_tenant_created_at_idx');
            }
            if (! $this->hasIndex('customers', 'customers_tenant_is_active_idx')) {
                $table->index(['tenant_id', 'is_active'], 'customers_tenant_is_active_idx');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! $this->hasIndex('stock_movements', 'stock_movements_tenant_created_at_idx')) {
                $table->index(['tenant_id', 'created_at'], 'stock_movements_tenant_created_at_idx');
            }
        });

        Schema::table('quotes', function (Blueprint $table) {
            if (! $this->hasIndex('quotes', 'quotes_tenant_status_idx')) {
                $table->index(['tenant_id', 'status'], 'quotes_tenant_status_idx');
            }
        });

        Schema::table('checkins', function (Blueprint $table) {
            if (! $this->hasIndex('checkins', 'checkins_tenant_checkin_time_idx')) {
                // Dashboard recent-activities + tire-hotel activity feeds.
                $table->index(['tenant_id', 'checkin_time'], 'checkins_tenant_checkin_time_idx');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! $this->hasIndex('audit_logs', 'audit_logs_tenant_created_at_idx')) {
                // For the archival command — filters `tenant_id + created_at < cutoff`.
                $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_at_idx');
            }
        });
    }

    public function down(): void
    {
        $drops = [
            'invoices' => [
                'invoices_tenant_status_due_idx',
                'invoices_tenant_issue_date_idx',
                'invoices_tenant_status_idx',
            ],
            'customers' => [
                'customers_tenant_created_at_idx',
                'customers_tenant_is_active_idx',
            ],
            'stock_movements' => ['stock_movements_tenant_created_at_idx'],
            'quotes' => ['quotes_tenant_status_idx'],
            'checkins' => ['checkins_tenant_checkin_time_idx'],
            'audit_logs' => ['audit_logs_tenant_created_at_idx'],
        ];

        foreach ($drops as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes) {
                foreach ($indexes as $name) {
                    if ($this->hasIndex($tableName, $name)) {
                        $table->dropIndex($name);
                    }
                }
            });
        }
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
