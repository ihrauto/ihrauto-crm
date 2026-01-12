<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTireRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:stored,ready_pickup,picked_up,maintenance,disposed',
            'storage_location' => 'sometimes|nullable|string|max:50',
            'brand' => 'sometimes|nullable|string|max:100',
            'model' => 'sometimes|nullable|string|max:100',
            'size' => 'sometimes|nullable|string|max:50',
            'season' => 'sometimes|in:summer,winter,all_season',
            'quantity' => 'sometimes|integer|min:1|max:8',
            'tread_depth_mm' => 'sometimes|nullable|numeric|min:0|max:20',
            'condition' => 'sometimes|nullable|in:excellent,good,fair,poor',
            'storage_fee' => 'sometimes|nullable|numeric|min:0',
            'storage_date' => 'sometimes|nullable|date',
            'expected_pickup_date' => 'sometimes|nullable|date',
            'pickup_reminder_date' => 'sometimes|nullable|date',
            'next_inspection_date' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Invalid tire status.',
            'season.in' => 'Invalid tire season type.',
            'condition.in' => 'Invalid tire condition.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 8.',
            'tread_depth_mm.max' => 'Tread depth cannot exceed 20mm.',
            'storage_fee.min' => 'Storage fee cannot be negative.',
        ];
    }

    /**
     * Get the validated data, allowing only safe fields.
     */
    public function safeFields(): array
    {
        return $this->only([
            'status',
            'storage_location',
            'brand',
            'model',
            'size',
            'season',
            'quantity',
            'tread_depth_mm',
            'condition',
            'storage_fee',
            'storage_date',
            'expected_pickup_date',
            'pickup_reminder_date',
            'next_inspection_date',
            'notes',
        ]);
    }
}
