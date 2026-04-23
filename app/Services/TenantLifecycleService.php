<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\TenantCache;
use Illuminate\Support\Facades\DB;

class TenantLifecycleService
{
    private const ARCHIVE_SOFT_DELETE_MODELS = [
        Appointment::class,
        Checkin::class,
        Customer::class,
        Product::class,
        Quote::class,
        Service::class,
        Tire::class,
        Vehicle::class,
        WorkOrder::class,
    ];

    public function archive(Tenant $tenant, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($tenant, $actor, $reason) {
            $tenantId = $tenant->id;

            foreach (self::ARCHIVE_SOFT_DELETE_MODELS as $modelClass) {
                $modelClass::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->chunkById(100, fn ($models) => $models->each->delete());
            }

            User::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->update(['is_active' => false]);

            $tenant->apiTokens()->whereNull('revoked_at')->get()->each->revoke();

            $tenant->forceFill(['is_active' => false])->save();
            TenantCache::forgetTenant($tenant);
            $tenant->delete();

            $this->log($tenant, $actor, 'tenant_archived', [
                'reason' => $reason,
                'tenant_name' => $tenant->name,
            ]);
        });
    }

    public function purge(Tenant $tenant, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($tenant, $actor, $reason) {
            $tenantId = $tenant->id;
            $userIds = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');

            if ($userIds->isNotEmpty()) {
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->whereIn('model_id', $userIds)
                    ->delete();

                DB::table('model_has_permissions')
                    ->where('model_type', User::class)
                    ->whereIn('model_id', $userIds)
                    ->delete();
            }

            DB::table('work_order_photos')->where('tenant_id', $tenantId)->delete();
            DB::table('service_bays')->where('tenant_id', $tenantId)->delete();
            DB::table('events')->where('tenant_id', $tenantId)->delete();
            DB::table('tenant_api_tokens')->where('tenant_id', $tenantId)->delete();
            DB::table('stock_movements')->where('tenant_id', (string) $tenantId)->delete();
            DB::table('storage_sections')->where('tenant_id', $tenantId)->delete();
            DB::table('warehouses')->where('tenant_id', $tenantId)->delete();
            DB::table('payments')->where('tenant_id', $tenantId)->delete();
            DB::table('invoice_items')->whereIn('invoice_id', function ($query) use ($tenantId) {
                $query->select('id')->from('invoices')->where('tenant_id', $tenantId);
            })->delete();
            DB::table('quote_items')->whereIn('quote_id', function ($query) use ($tenantId) {
                $query->select('id')->from('quotes')->where('tenant_id', $tenantId);
            })->delete();
            DB::table('invoices')->where('tenant_id', $tenantId)->delete();
            DB::table('quotes')->where('tenant_id', $tenantId)->delete();
            DB::table('work_orders')->where('tenant_id', $tenantId)->delete();
            DB::table('appointments')->where('tenant_id', $tenantId)->delete();
            DB::table('checkins')->where('tenant_id', $tenantId)->delete();
            DB::table('tires')->where('tenant_id', $tenantId)->delete();
            DB::table('vehicles')->where('tenant_id', $tenantId)->delete();
            DB::table('customers')->where('tenant_id', $tenantId)->delete();
            DB::table('products')->where('tenant_id', $tenantId)->delete();
            DB::table('services')->where('tenant_id', $tenantId)->delete();
            DB::table('users')->where('tenant_id', $tenantId)->delete();

            TenantCache::forgetTenant($tenant);
            $this->log($tenant, $actor, 'tenant_purged', [
                'reason' => $reason,
                'tenant_name' => $tenant->name,
            ]);

            $tenant->forceDelete();
        });
    }

    private function log(Tenant $tenant, ?User $actor, string $action, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'model_type' => Tenant::class,
            'model_id' => (string) $tenant->id,
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);
    }
}
