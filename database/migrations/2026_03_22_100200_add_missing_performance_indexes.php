<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing performance indexes identified during code review.
     */
    public function up(): void
    {
        // Payments: composite index for date-range finance queries
        if (Schema::hasColumn('payments', 'payment_date')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['tenant_id', 'payment_date']);
            });
        }

        // Invoice items: FK index for invoice lookup
        if (! $this->hasIndex('invoice_items', 'invoice_items_invoice_id_index')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->index('invoice_id');
            });
        }

        // Products: composite for active product listings
        if (Schema::hasColumn('products', 'is_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_active']);
            });
        }

        // Services: composite for active service listings
        if (Schema::hasColumn('services', 'is_active')) {
            Schema::table('services', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_active']);
            });
        }

        // Stock movements: composite for movement type filtering
        if (Schema::hasColumn('stock_movements', 'type')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(['tenant_id', 'type']);
            });
        }

        // Work order photos: composite for photo attribution
        Schema::table('work_order_photos', function (Blueprint $table) {
            $table->index(['work_order_id', 'user_id']);
        });

        // Audit logs: composite for user activity lookup
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex('payments', 'payments_tenant_id_payment_date_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'payment_date']);
            });
        }

        if ($this->hasIndex('invoice_items', 'invoice_items_invoice_id_index')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropIndex(['invoice_id']);
            });
        }

        if ($this->hasIndex('products', 'products_tenant_id_is_active_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'is_active']);
            });
        }

        if ($this->hasIndex('services', 'services_tenant_id_is_active_index')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'is_active']);
            });
        }

        if ($this->hasIndex('stock_movements', 'stock_movements_tenant_id_type_index')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'type']);
            });
        }

        if ($this->hasIndex('work_order_photos', 'work_order_photos_work_order_id_user_id_index')) {
            Schema::table('work_order_photos', function (Blueprint $table) {
                $table->dropIndex(['work_order_id', 'user_id']);
            });
        }

        if ($this->hasIndex('audit_logs', 'audit_logs_user_id_created_at_index')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'created_at']);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
