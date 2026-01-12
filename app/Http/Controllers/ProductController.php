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
        ]);

        $validated['tenant_id'] = tenant()->id;

        $product = Product::create($validated);

        // Initial Stock Movement log if quantity > 0
        if ($validated['stock_quantity'] > 0) {
            StockMovement::create([
                'tenant_id' => tenant()->id,
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
        ]);

        $product->update($validated);

        return redirect()->route('products-services.index', ['tab' => 'parts'])
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
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

        $qty = $validated['quantity'];
        if ($validated['type'] === 'remove') {
            $qty = -$qty;
        }

        DB::transaction(function () use ($product, $qty, $validated) {
            $product->stock_quantity += $qty;
            $product->save();

            StockMovement::create([
                'tenant_id' => tenant()->id,
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
}
