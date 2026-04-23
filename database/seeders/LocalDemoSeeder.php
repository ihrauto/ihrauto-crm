<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            TenantSeeder::class,
            StorageLayoutSeeder::class,
            ProductServiceSeeder::class,
        ]);

        $this->command->info('Local demo data seeded successfully.');
    }
}
