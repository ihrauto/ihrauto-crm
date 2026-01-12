<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkOrderRequest extends FormRequest
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
            'status' => 'sometimes|in:created,pending,in_progress,completed,cancelled',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'technician_id' => 'nullable|exists:users,id',
            'technician_notes' => 'nullable|string|max:2000',
            'customer_issues' => 'nullable|string|max:2000',

            // Service tasks (JSON array)
            'service_tasks' => 'nullable|array',
            'service_tasks.*.name' => 'required_with:service_tasks|string|max:255',
            'service_tasks.*.price' => 'nullable|numeric|min:0',
            'service_tasks.*.completed' => 'nullable|boolean',

            // Parts used (JSON array)
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
            'status.in' => 'Invalid work order status.',
            'priority.in' => 'Invalid priority level.',
            'technician_id.exists' => 'The selected technician does not exist.',
            'technician_notes.max' => 'Technician notes cannot exceed 2000 characters.',
            'service_tasks.*.name.required_with' => 'Each service task must have a name.',
            'service_tasks.*.price.numeric' => 'Service task price must be a number.',
            'service_tasks.*.price.min' => 'Service task price cannot be negative.',
            'parts_used.*.name.required_with' => 'Each part must have a name.',
            'parts_used.*.qty.integer' => 'Part quantity must be a whole number.',
            'parts_used.*.qty.min' => 'Part quantity must be at least 1.',
            'parts_used.*.price.numeric' => 'Part price must be a number.',
            'parts_used.*.price.min' => 'Part price cannot be negative.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure arrays are properly decoded if passed as JSON strings
        if ($this->has('service_tasks') && is_string($this->service_tasks)) {
            $this->merge([
                'service_tasks' => json_decode($this->service_tasks, true),
            ]);
        }

        if ($this->has('parts_used') && is_string($this->parts_used)) {
            $this->merge([
                'parts_used' => json_decode($this->parts_used, true),
            ]);
        }
    }
}
