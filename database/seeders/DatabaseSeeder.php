<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(\Database\Seeders\InitialAdminSeeder::class);

        // 1. Seed Tenants and Users (Admin)
        $this->call(TenantSeeder::class);

        // 2. Seed Infrastructure (Storage Racks)
        $this->call(StorageLayoutSeeder::class);

        // 3. Seed Services and Parts (Catalog)
        $this->call(ProductServiceSeeder::class);

        $this->command->info('Database seeded successfully! (Clean Install)');
    }
}
