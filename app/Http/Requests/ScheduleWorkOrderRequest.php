<?php

namespace App\Http\Requests;

use App\Rules\TechnicianAvailable;
use App\Support\TenantValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * C-01 part 2: validation for the "Schedule Job" form extracted from
 * WorkOrderController::store so the controller can focus on orchestration.
 *
 * Separate from StoreWorkOrderRequest (which validates ad-hoc work-order
 * payloads with tasks/parts). This request is for scheduling a future
 * job, so it requires scheduled_at + a bay + a technician window.
 *
 * Plugs in the TechnicianAvailable rule (C-06) to replace the duplicated
 * `isTechnicianBusy()` check previously done inline in the controller.
 */
class ScheduleWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy authorization still runs in the controller via
        // $this->authorize('create', WorkOrder::class); this request only
        // enforces payload shape.
        return true;
    }

    public function rules(): array
    {
        $bayCount = (int) config('crm.service_bays.count', 6);

        return [
            'customer_id' => ['required', TenantValidation::exists('customers')],
            'vehicle_id' => ['required', TenantValidation::exists('vehicles')],
            'scheduled_at' => ['required', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'service_bay' => ['nullable', 'integer', 'between:1,'.$bayCount],
            'service_description' => ['required', 'string', 'max:2000'],
            'technician_id' => [
                'nullable',
                TenantValidation::exists('users'),
                new TechnicianAvailable,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer.',
            'vehicle_id.required' => 'Please select a vehicle.',
            'scheduled_at.required' => 'A scheduled start time is required.',
            'service_description.required' => 'Describe the work to be performed.',
            'estimated_minutes.min' => 'Estimated duration must be at least 15 minutes.',
            'estimated_minutes.max' => 'Estimated duration must be 8 hours or less.',
            'service_bay.between' => 'Select a valid service bay.',
        ];
    }
}
