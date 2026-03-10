<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantLifecycleService;
use Illuminate\Console\Command;

class PurgeTenantCommand extends Command
{
    protected $signature = 'tenant:purge {tenant : Tenant ID} {--reason= : Audit reason}';

    protected $description = 'Irreversibly purge a tenant and all associated data.';

    public function handle(TenantLifecycleService $tenantLifecycleService): int
    {
        $tenant = Tenant::withTrashed()->findOrFail($this->argument('tenant'));

        if (! $this->confirm("This will permanently delete tenant {$tenant->id} ({$tenant->name}) and all associated data. Continue?")) {
            return self::FAILURE;
        }

        $tenantLifecycleService->purge($tenant, null, $this->option('reason'));

        $this->info('Tenant purge complete.');

        return self::SUCCESS;
    }
}
