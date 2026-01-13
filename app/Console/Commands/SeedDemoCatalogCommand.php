<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SeedDemoCatalogCommand extends Command
{
    protected $signature = 'crm:seed-demo-catalog 
                            {--tenant=all : Tenant ID or "all" for all tenants}
                            {--force : Overwrite existing template items}';

    protected $description = 'Seed example products and services for demo/onboarding';

    protected array $products = [
        [
            'name' => 'Engine Oil 5W-30 (5L)',
            'sku' => 'OIL-5W30-5L',
            'price' => 49.90,
            'stock_quantity' => 20,
            'min_stock_quantity' => 5,
            'description' => 'High-quality synthetic motor oil, 5 liter container',
            'is_template' => true,
        ],
        [
            'name' => 'Oil Filter',
            'sku' => 'FLT-OIL-STD',
            'price' => 12.50,
            'stock_quantity' => 50,
            'min_stock_quantity' => 10,
            'description' => 'Standard oil filter, fits most vehicles',
            'is_template' => true,
        ],
        [
            'name' => 'Brake Pads (Front)',
            'sku' => 'BRK-PAD-FRT',
            'price' => 89.00,
            'stock_quantity' => 15,
            'min_stock_quantity' => 4,
            'description' => 'Premium front brake pads set',
            'is_template' => true,
        ],
        [
            'name' => 'Car Battery 70Ah',
            'sku' => 'BAT-70AH-12V',
            'price' => 149.00,
            'stock_quantity' => 8,
            'min_stock_quantity' => 2,
            'description' => '12V 70Ah maintenance-free car battery',
            'is_template' => true,
        ],
    ];

    protected array $services = [
        [
            'name' => 'Oil Change (Oil + Filter)',
            'code' => 'SVC-OIL',
            'price' => 39.00,
            'description' => 'Complete oil change service including oil and filter replacement',
            'category' => 'Maintenance',
            'is_active' => true,
            'is_template' => true,
        ],
        [
            'name' => 'Brake Pads Replacement (Front)',
            'code' => 'SVC-BRK-FRT',
            'price' => 120.00,
            'description' => 'Front brake pads replacement labor',
            'category' => 'Brakes',
            'is_active' => true,
            'is_template' => true,
        ],
        [
            'name' => 'Tire Change (4 tires)',
            'code' => 'SVC-TIRE-4',
            'price' => 40.00,
            'description' => 'Mounting and balancing of 4 tires',
            'category' => 'Tires',
            'is_active' => true,
            'is_template' => true,
        ],
        [
            'name' => 'Diagnostics Scan',
            'code' => 'SVC-DIAG',
            'price' => 35.00,
            'description' => 'Full OBD-II diagnostic scan and report',
            'category' => 'Diagnostics',
            'is_active' => true,
            'is_template' => true,
        ],
    ];

    public function handle(): int
    {
        $tenantOption = $this->option('tenant');
        $force = $this->option('force');

        // Get tenants to process
        $tenants = $tenantOption === 'all'
            ? Tenant::all()
            : Tenant::where('id', $tenantOption)->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return Command::FAILURE;
        }

        $this->info("Seeding demo catalog for {$tenants->count()} tenant(s)...\n");

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $force);
        }

        $this->newLine();
        $this->info('✅ Demo catalog seeding complete!');

        return Command::SUCCESS;
    }

    protected function processTenant(Tenant $tenant, bool $force): void
    {
        $this->info("📦 Tenant: {$tenant->name} (ID: {$tenant->id})");

        $productsCreated = 0;
        $servicesCreated = 0;

        // Seed Products
        foreach ($this->products as $productData) {
            $exists = Product::where('tenant_id', $tenant->id)
                ->where('sku', $productData['sku'])
                ->exists();

            if ($exists && !$force) {
                $this->line("   ⏭️  Product exists: {$productData['name']}");
                continue;
            }

            if ($exists && $force) {
                Product::where('tenant_id', $tenant->id)
                    ->where('sku', $productData['sku'])
                    ->delete();
            }

            Product::create(array_merge($productData, ['tenant_id' => $tenant->id]));
            $this->line("   ✅ Product created: {$productData['name']}");
            $productsCreated++;
        }

        // Seed Services
        foreach ($this->services as $serviceData) {
            $exists = Service::where('tenant_id', $tenant->id)
                ->where('code', $serviceData['code'])
                ->exists();

            if ($exists && !$force) {
                $this->line("   ⏭️  Service exists: {$serviceData['name']}");
                continue;
            }

            if ($exists && $force) {
                Service::where('tenant_id', $tenant->id)
                    ->where('code', $serviceData['code'])
                    ->delete();
            }

            Service::create(array_merge($serviceData, ['tenant_id' => $tenant->id]));
            $this->line("   ✅ Service created: {$serviceData['name']}");
            $servicesCreated++;
        }

        $this->line("   📊 Created: {$productsCreated} products, {$servicesCreated} services\n");
    }
}
