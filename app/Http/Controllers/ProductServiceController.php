<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;

class ProductServiceController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'parts');

        $products = Product::latest()->get(); // For MVP, simple get() is fine. Pagination later.
        $services = Service::latest()->get();

        return view('products-services.index', compact('products', 'services', 'tab'));
    }

    public function search(Request $request)
    {
        $q = $request->get('q');

        $products = Product::where('name', 'like', "%{$q}%")
            ->orWhere('sku', 'like', "%{$q}%")
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price' => $p->price,
                    'type' => 'product', // Inventory Part
                    'stock' => $p->stock_quantity,
                    'label' => "[Part] {$p->name} (Stock: {$p->stock_quantity}) - CHF {$p->price}",
                ];
            });

        $services = Service::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            })
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'sku' => $s->code,
                    'price' => $s->price,
                    'type' => 'service', // Service/Labor
                    'stock' => null,
                    'label' => "[Svc] {$s->name} - CHF {$s->price}",
                ];
            });

        return response()->json($products->concat($services));
    }
}
