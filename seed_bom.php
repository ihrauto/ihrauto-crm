<?php

use App\Models\Product;
use App\Models\Service;

// Find existing Service or use first
$service = Service::where('name', 'Synthetic Oil Change')->first();

if (! $service) {
    echo "Service 'Synthetic Oil Change' not found. Listing available services:\n";
    Service::all()->each(fn ($s) => print ("- {$s->name}\n"));
    exit;
}

// Find or create products
$oilFilter = Product::firstOrCreate(
    ['name' => 'Oil Filter (Standard)'],
    ['tenant_id' => 1, 'price' => 15.00, 'stock_quantity' => 50, 'min_stock_quantity' => 5]
);

$syntheticOil = Product::firstOrCreate(
    ['name' => 'Synthetic Oil 5W-30 (5L)'],
    ['tenant_id' => 1, 'price' => 45.00, 'stock_quantity' => 30, 'min_stock_quantity' => 5]
);

// Link products to service (BOM)
$service->products()->sync([
    $oilFilter->id => ['quantity' => 1],
    $syntheticOil->id => ['quantity' => 1],
]);

echo "Linked '{$service->name}' to:\n";
foreach ($service->products as $p) {
    echo "- {$p->name} (Qty: {$p->pivot->quantity})\n";
}

echo "\nInitial Stock:\n";
echo "- Oil Filter: {$oilFilter->stock_quantity}\n";
echo "- Synthetic Oil: {$syntheticOil->stock_quantity}\n";

echo "\nBOM setup complete. Now perform a Check-in with '{$service->name}' and complete the Work Order to test deduction.\n";
