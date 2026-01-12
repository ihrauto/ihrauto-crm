<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewTireCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'vehicle_info' => 'required|string|max:200',
            'registration' => 'required|string|max:15',
            'brand' => 'required|string|max:50',
            'model' => 'nullable|string|max:50',
            'size' => 'required|string|max:20',
            'season' => 'required|in:winter,summer,all_season,race',
            'quantity' => 'required|integer|min:1|max:8',
            'storage_location' => [
                'required',
                'string',
                'max:50',
                \Illuminate\Validation\Rule::unique('tires', 'storage_location')->where(function ($query) {
                    return $query->where('status', 'stored');
                }),
            ],
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
