<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'min_stock_quantity' => 'required|integer|min:0',
            'description' => 'nullable|string|max:2000',
            'stock_quantity' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'purchase_price' => 'nullable|numeric|min:0',
            'order_number' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:in_stock,out_of_stock,ordered',
        ];
    }
}
