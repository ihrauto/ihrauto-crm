<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;

// Setup Data
$tenantId = User::first()->tenant_id;
$user = User::first();
auth()->login($user);

// 1. Create a Product
$product = Product::create([
    'tenant_id' => $tenantId,
    'name' => 'Test Oil Filter',
    'price' => 10.00,
    'stock_quantity' => 100,
    'min_stock_quantity' => 10,
]);

echo 'Initial Stock: '.$product->stock_quantity."\n";

// 2. Create Customer and Vehicle
$customer = Customer::first() ?? Customer::create([
    'tenant_id' => $tenantId,
    'name' => 'Test Customer',
    'email' => 'test@example.com',
    'phone' => '1234567890',
    'address' => '123 Main St',
]);

$vehicle = Vehicle::first() ?? Vehicle::create([
    'tenant_id' => $tenantId,
    'customer_id' => $customer->id,
    'license_plate' => 'TEST-123',
    'make' => 'Toyota',
    'model' => 'Camry',
    'year' => 2020,
    'color' => 'White',
]);

// 3. Create Work Order
$wo = WorkOrder::create([
    'tenant_id' => $tenantId,
    'customer_id' => $customer->id,
    'vehicle_id' => $vehicle->id,
    'status' => 'in_progress',
    'parts_used' => [
        [
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 5,
            'price' => 10.00,
        ],
    ],
]);

// 3. Complete Work Order (simulating Controller logic)
$service = new InvoiceService;

DB::transaction(function () use ($wo, $service) {
    // This logic mimics WorkOrderController::completeWorkOrder
    $service->processStockDeductions($wo);
    $wo->update(['status' => 'completed', 'completed_at' => now()]);
});

// 4. Check Stock
$product->refresh();
echo 'Final Stock: '.$product->stock_quantity."\n";

if ($product->stock_quantity === 95) {
    echo "SUCCESS: Stock deducted correctly.\n";
} else {
    echo "FAILURE: Stock not deducted.\n";
}
