<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
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
            'checkin_id' => 'sometimes|exists:checkins,id',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'technician_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|in:created,pending,in_progress,completed,cancelled',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'customer_issues' => 'nullable|string|max:2000',
            'technician_notes' => 'nullable|string|max:2000',
            'service_tasks' => 'nullable|array',
            'service_tasks.*.name' => 'required_with:service_tasks|string|max:255',
            'service_tasks.*.price' => 'nullable|numeric|min:0',
            'service_tasks.*.completed' => 'nullable|boolean',
            'parts_used' => 'nullable|array',
            'parts_used.*.name' => 'required_with:parts_used|string|max:255',
            'parts_used.*.qty' => 'nullable|integer|min:1',
            'parts_used.*.price' => 'nullable|numeric|min:0',
            'parts_used.*.product_id' => 'nullable|exists:products,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'A customer is required for the work order.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'vehicle_id.required' => 'A vehicle is required for the work order.',
            'vehicle_id.exists' => 'The selected vehicle does not exist.',
            'technician_id.exists' => 'The selected technician does not exist.',
            'status.in' => 'Invalid work order status.',
            'priority.in' => 'Invalid priority level.',
            'service_tasks.*.name.required_with' => 'Each service task must have a name.',
            'service_tasks.*.price.numeric' => 'Service task price must be a number.',
            'parts_used.*.name.required_with' => 'Each part must have a name.',
            'parts_used.*.qty.integer' => 'Part quantity must be a whole number.',
            'parts_used.*.price.numeric' => 'Part price must be a number.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'vehicle_id' => 'vehicle',
            'technician_id' => 'technician',
            'customer_issues' => 'customer issues',
            'technician_notes' => 'technician notes',
            'service_tasks' => 'service tasks',
            'parts_used' => 'parts used',
        ];
    }
}
