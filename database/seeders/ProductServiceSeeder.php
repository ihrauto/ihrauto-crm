<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ProductServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have a tenant
        $tenantId = Tenant::first()->id ?? Tenant::create(['name' => 'Default Tenant'])->id;

        // SERVICES
        $services = [
            // Oil Services
            [
                'name' => 'Synthetic Oil Change',
                'category' => 'oil',
                'code' => 'OIL-SYN',
                'price' => 89.99,
                'description' => 'Full synthetic oil change up to 5L with filter',
                'is_active' => true,
            ],
            [
                'name' => 'Standard Oil Change',
                'category' => 'oil',
                'code' => 'OIL-STD',
                'price' => 49.99,
                'description' => 'Conventional oil change up to 5L with filter',
                'is_active' => true,
            ],
            [
                'name' => 'Oil Filter Replacement',
                'category' => 'oil',
                'code' => 'OIL-FIL',
                'price' => 15.00,
                'description' => 'Replace oil filter only',
                'is_active' => true,
            ],

            // Brake Services
            [
                'name' => 'Brake Pad Replacement (Front)',
                'category' => 'brakes',
                'code' => 'BRK-PAD-F',
                'price' => 120.00,
                'description' => 'Replace front brake pads (labor only)',
                'is_active' => true,
            ],
            [
                'name' => 'Brake Pad Replacement (Rear)',
                'category' => 'brakes',
                'code' => 'BRK-PAD-R',
                'price' => 120.00,
                'description' => 'Replace rear brake pads (labor only)',
                'is_active' => true,
            ],
            [
                'name' => 'Brake Disc Resurfacing',
                'category' => 'brakes',
                'code' => 'BRK-DISC',
                'price' => 80.00,
                'description' => 'Resurface brake rotors per axle',
                'is_active' => true,
            ],

            // Tire Services
            [
                'name' => 'Tire Mounting & Balancing (Per Tire)',
                'category' => 'tires',
                'code' => 'TIRE-MNT',
                'price' => 25.00,
                'description' => 'Mount and balance one tire',
                'is_active' => true,
            ],
            [
                'name' => 'Tire Rotation',
                'category' => 'tires',
                'code' => 'TIRE-ROT',
                'price' => 29.99,
                'description' => 'Rotate all 4 tires',
                'is_active' => true,
            ],
            [
                'name' => 'Wheel Alignment',
                'category' => 'tires',
                'code' => 'ALIGN',
                'price' => 99.00,
                'description' => '4-wheel alignment',
                'is_active' => true,
            ],

            // Engine Services
            [
                'name' => 'Spark Plug Replacement (4 cyl)',
                'category' => 'engine',
                'code' => 'SPARK-4',
                'price' => 80.00,
                'description' => 'Labor for replacing 4 spark plugs',
                'is_active' => true,
            ],
            [
                'name' => 'Air Filter Replacement',
                'category' => 'engine',
                'code' => 'AIR-FIL',
                'price' => 15.00,
                'description' => 'Replace engine air filter',
                'is_active' => true,
            ],

            // Electrical
            [
                'name' => 'Battery Replacement',
                'category' => 'electrical',
                'code' => 'BATT-REP',
                'price' => 30.00,
                'description' => 'Install new battery (labor only)',
                'is_active' => true,
            ],
            [
                'name' => 'Diagnostic Scan',
                'category' => 'electrical',
                'code' => 'DIAG',
                'price' => 85.00,
                'description' => 'OBDII computer diagnostic scan',
                'is_active' => true,
            ],

            // General Maintenance
            [
                'name' => 'General Inspection',
                'category' => 'maintenance',
                'code' => 'INSPECT',
                'price' => 45.00,
                'description' => 'Multi-point vehicle inspection',
                'is_active' => true,
            ],
            [
                'name' => 'AC Recharge',
                'category' => 'maintenance',
                'code' => 'AC-RCHG',
                'price' => 120.00,
                'description' => 'Recharge AC system refrigerant',
                'is_active' => true,
            ],
        ];

        foreach ($services as $service) {
            $data = array_merge($service, ['tenant_id' => $tenantId]);
            Service::updateOrCreate(
                ['code' => $service['code'], 'tenant_id' => $tenantId],
                $data
            );
        }

        // PRODUCTS (PARTS)
        $products = [
            [
                'name' => 'Synthetic Motor Oil 5W30 (1L)',
                'sku' => 'OIL-5W30-1L',
                'description' => 'High performance synthetic oil',
                'price' => 12.50,
                'stock_quantity' => 100,
                'min_stock_quantity' => 20,
            ],
            [
                'name' => 'Oil Filter (Universal)',
                'sku' => 'FIL-OIL-UNIV',
                'description' => 'Standard oil filter',
                'price' => 8.50,
                'stock_quantity' => 50,
                'min_stock_quantity' => 10,
            ],
            [
                'name' => 'Air Filter (Type A)',
                'sku' => 'FIL-AIR-A',
                'description' => 'Engine air filter for compact cars',
                'price' => 18.00,
                'stock_quantity' => 30,
                'min_stock_quantity' => 5,
            ],
            [
                'name' => 'Brake Pads (Front Set)',
                'sku' => 'BRK-PAD-F-SET',
                'description' => 'Ceramic brake pads front set',
                'price' => 45.00,
                'stock_quantity' => 20,
                'min_stock_quantity' => 4,
            ],
            [
                'name' => 'Car Battery 60Ah',
                'sku' => 'BATT-60AH',
                'description' => 'Maintenance free battery 12V 60Ah',
                'price' => 110.00,
                'stock_quantity' => 10,
                'min_stock_quantity' => 2,
            ],
            [
                'name' => 'Wiper Blade 24"',
                'sku' => 'WIPER-24',
                'description' => 'All-season wiper blade',
                'price' => 14.00,
                'stock_quantity' => 40,
                'min_stock_quantity' => 10,
            ],
            [
                'name' => 'Spark Plug (Iridium)',
                'sku' => 'SPARK-IR',
                'description' => 'Long life iridium spark plug',
                'price' => 12.00,
                'stock_quantity' => 80,
                'min_stock_quantity' => 16,
            ],
        ];

        foreach ($products as $product) {
            $data = array_merge($product, ['tenant_id' => $tenantId]);
            Product::updateOrCreate(
                ['sku' => $product['sku'], 'tenant_id' => $tenantId],
                $data
            );
        }
    }
}
