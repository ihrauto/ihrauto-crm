<?php

namespace Database\Seeders;

use App\Models\StorageSection;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class StorageLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Create Main Warehouse
            $warehouse = Warehouse::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Main Warehouse'],
                [
                    'description' => 'Default storage location',
                    'is_active' => true,
                ]
            );

            // Create Sections A-D
            $sections = ['A', 'B', 'C', 'D'];

            foreach ($sections as $sectionName) {
                StorageSection::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'warehouse_id' => $warehouse->id,
                        'name' => $sectionName,
                    ],
                    [
                        'capacity_slots' => 20,
                        'type' => 'standard',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
