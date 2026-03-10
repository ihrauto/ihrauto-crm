<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BootstrapSuperAdminCommand extends Command
{
    protected $signature = 'ops:bootstrap-super-admin';

    protected $description = 'Seed roles and the configured super-admin account.';

    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
        $this->callSilently('db:seed', ['--class' => 'SuperAdminSeeder', '--force' => true]);

        $this->info('Super-admin bootstrap complete.');

        return self::SUCCESS;
    }
}
