<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function store(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        $validated['tenant_id'] = tenant_id();

        $product = Product::create($validated);

        // Initial Stock Movement log if quantity > 0
        if ($validated['stock_quantity'] > 0) {
            StockMovement::create([
                'tenant_id' => tenant_id(),
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

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

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
        $this->authorize('adjustStock', $product);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'type' => 'required|in:add,remove', // add, remove (visual in modal) -> maps to stock logic
            'notes' => 'nullable|string',
        ]);

        // Validate removal doesn't exceed available stock
        if ($validated['type'] === 'remove' && $validated['quantity'] > $product->stock_quantity) {
            return back()->with('error', 'Cannot remove more stock than available ('.$product->stock_quantity.' in stock).');
        }

        $qty = $validated['quantity'];
        if ($validated['type'] === 'remove') {
            $qty = -$qty;
        }

        DB::transaction(function () use ($product, $qty, $validated) {
            $product->stock_quantity += $qty;
            $product->save();

            StockMovement::create([
                'tenant_id' => tenant_id(),
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

    public function import(ImportProductsRequest $request)
    {
        $this->authorize('create', Product::class);

        $file = $request->file('file');

        if (($handle = fopen($file->getRealPath(), 'r')) === false) {
            return back()->with('error', 'Could not read the file.');
        }

        $header = fgetcsv($handle, 1000, ',');

        // Validate CSV headers
        $expectedHeaders = ['Name', 'SKU', 'Price', 'Quantity', 'Min Stock'];
        $normalizedHeaders = array_map(fn ($h) => strtolower(trim($h ?? '')), $header ?: []);
        $normalizedExpected = array_map('strtolower', $expectedHeaders);

        if (count($normalizedHeaders) < 1 || $normalizedHeaders[0] !== $normalizedExpected[0]) {
            fclose($handle);

            return back()->with('error', 'Invalid CSV format. Expected headers: '.implode(', ', $expectedHeaders).'. Download the template for the correct format.');
        }

        $count = 0;
        $errors = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (count($data) < 1 || empty(trim($data[0] ?? ''))) {
                continue;
            }

            // Row-level validation
            $name = trim($data[0]);
            $price = isset($data[2]) ? (float) $data[2] : 0;

            if (strlen($name) > 255) {
                $errors[] = "Row {$rowNumber}: Name exceeds 255 characters.";

                continue;
            }

            if ($price < 0) {
                $errors[] = "Row {$rowNumber}: Price cannot be negative.";

                continue;
            }

            Product::create([
                'tenant_id' => tenant_id(),
                'name' => $name,
                'sku' => isset($data[1]) ? trim($data[1]) : null,
                'price' => $price,
                'stock_quantity' => isset($data[3]) ? max(0, (int) $data[3]) : 0,
                'min_stock_quantity' => isset($data[4]) ? max(0, (int) $data[4]) : 10,
                'description' => 'Imported via CSV',
            ]);

            $count++;
        }
        fclose($handle);

        $message = "Imported {$count} products successfully.";
        if (! empty($errors)) {
            $message .= ' '.count($errors).' rows skipped due to errors: '.implode(' | ', array_slice($errors, 0, 5));
        }

        return redirect()->route('products-services.index', ['tab' => 'parts'])
            ->with($count > 0 ? 'success' : 'error', $message);
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
