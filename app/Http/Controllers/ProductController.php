<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'min_stock_quantity' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'purchase_price' => 'nullable|numeric|min:0',
            'order_number' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:in_stock,out_of_stock,ordered',
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;

        $product = Product::create($validated);

        // Initial Stock Movement log if quantity > 0
        if ($validated['stock_quantity'] > 0) {
            StockMovement::create([
                'tenant_id' => auth()->user()->tenant_id,
                'product_id' => $product->id,
                'quantity' => $validated['stock_quantity'],
                'type' => 'initial',
                'user_id' => auth()->id(),
                'notes' => 'Initial stock on creation',
            ]);
        }

        return redirect()->route('products-services.index', ['tab' => 'parts'])
            ->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'min_stock_quantity' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'stock_quantity' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'purchase_price' => 'nullable|numeric|min:0',
            'order_number' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:in_stock,out_of_stock,ordered',
        ]);

        $product->update($validated);

        return redirect()->route('products-services.index', ['tab' => 'parts'])
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        $product->delete();

        return redirect()->route('products-services.index', ['tab' => 'parts'])
            ->with('success', 'Product deleted.');
    }

    public function stockOperation(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'type' => 'required|in:add,remove', // add, remove (visual in modal) -> maps to stock logic
            'notes' => 'nullable|string',
        ]);

        // Validate removal doesn't exceed available stock
        if ($validated['type'] === 'remove' && $validated['quantity'] > $product->stock_quantity) {
            return back()->with('error', 'Cannot remove more stock than available (' . $product->stock_quantity . ' in stock).');
        }

        $qty = $validated['quantity'];
        if ($validated['type'] === 'remove') {
            $qty = -$qty;
        }

        DB::transaction(function () use ($product, $qty, $validated) {
            $product->stock_quantity += $qty;
            $product->save();

            StockMovement::create([
                'tenant_id' => auth()->user()->tenant_id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'type' => $validated['type'] === 'add' ? 'purchase' : 'correction', // simplistic mapping
                'user_id' => auth()->id(),
                'notes' => $validated['notes'] ?? 'Manual stock operation',
            ]);
        });

        return redirect()->route('products-services.index', ['tab' => 'parts'])
            ->with('success', 'Stock updated successfully.');
    }
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120', // Max 5MB
        ]);

        $file = $request->file('file');

        // Open file
        if (($handle = fopen($file->getRealPath(), 'r')) !== FALSE) {
            $header = fgetcsv($handle, 1000, ','); // Assume first row is header

            // Basic mapping check (optional, or just assume order/names)
            // For now, let's assume columns: Name, SKU, Price, Quantity, MinStock

            $count = 0;

            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                // Skip empty rows
                if (count($data) < 1 || empty($data[0]))
                    continue;

                // Simple mapping by index for MVP (or can be header based later)
                // 0: Name, 1: SKU, 2: Price, 3: Qty, 4: MinStock

                Product::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'name' => $data[0],
                    'sku' => $data[1] ?? null,
                    'price' => isset($data[2]) ? (float) $data[2] : 0,
                    'stock_quantity' => isset($data[3]) ? (int) $data[3] : 0,
                    'min_stock_quantity' => isset($data[4]) ? (int) $data[4] : 10,
                    'description' => 'Imported via Excel/CSV',
                ]);

                $count++;
            }
            fclose($handle);

            return redirect()->route('products-services.index', ['tab' => 'parts'])
                ->with('success', "Imported {$count} products successfully.");
        }

        return back()->with('error', 'Could not read the file.');
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_template.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // Headers
            fputcsv($handle, ['Name', 'SKU', 'Price', 'Quantity', 'Min Stock']);
            // Sample Row
            fputcsv($handle, ['Brake Pad', 'BP-001', '45.50', '100', '5']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
