<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantApiToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RotateTenantApiTokenCommand extends Command
{
    protected $signature = 'tenant:rotate-api-token {tenant : Tenant ID} {--name=default : Token name to assign} {--keep-old : Keep existing active tokens}';

    protected $description = 'Rotate the bearer token used to access tenant API routes.';

    public function handle(): int
    {
        $tenant = Tenant::withoutTrashed()->findOrFail($this->argument('tenant'));

        [$tokenModel, $plainTextToken] = DB::transaction(function () use ($tenant) {
            if (! $this->option('keep-old')) {
                $tenant->apiTokens()->whereNull('revoked_at')->get()->each->revoke();
            }

            return TenantApiToken::issue($tenant, (string) $this->option('name'));
        });

        $this->info("Issued API token {$tokenModel->token_prefix} for tenant {$tenant->id}.");
        $this->line('Store this token now. It will not be shown again:');
        $this->line($plainTextToken);

        return self::SUCCESS;
    }
}
