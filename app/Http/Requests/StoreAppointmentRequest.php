<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Support\TenantValidation;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'customer_id' => ['nullable', TenantValidation::exists('customers')],
            'vehicle_id' => ['nullable', TenantValidation::exists('vehicles')],
            'start_date' => 'required|date',
            'start_time' => 'required',
            'duration' => 'required|integer|min:15|max:480',
            'type' => 'required|string|in:tire_change,oil_change,inspection,maintenance,repair,other',
            'title' => 'nullable|string|max:255',
            'status' => 'required|in:scheduled,confirmed',
            'notes' => 'nullable|string|max:2000',
        ];

        // New customer fields
        if (empty($this->customer_id) && $this->filled('new_customer_name')) {
            $rules['new_customer_name'] = 'required|string|max:255';
            $rules['new_customer_phone'] = 'required|string|max:20';
        }

        return $rules;
    }

    /**
     * Additional validation after standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkForConflicts($validator);
        });
    }

    /**
     * Check for overlapping appointments on the same vehicle.
     */
    protected function checkForConflicts($validator): void
    {
        $vehicleId = $this->vehicle_id;
        if (! $vehicleId) {
            return;
        }

        $startDateTime = Carbon::parse($this->start_date.' '.$this->start_time);
        $endDateTime = $startDateTime->copy()->addMinutes((int) $this->duration);

        $conflict = Appointment::where('vehicle_id', $vehicleId)
            ->whereNotIn('status', ['cancelled', 'completed', 'failed'])
            ->where('start_time', '<', $endDateTime)
            ->where('end_time', '>', $startDateTime)
            ->first();

        if ($conflict) {
            $conflictTime = $conflict->start_time->format('M j, g:i A');
            $validator->errors()->add(
                'start_time',
                "This vehicle already has an appointment at {$conflictTime}. Please choose a different time."
            );
        }
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Please select a date.',
            'start_time.required' => 'Please select a time.',
            'duration.required' => 'Duration is required.',
            'duration.min' => 'Duration must be at least 15 minutes.',
            'type.required' => 'Please select an appointment type.',
            'type.in' => 'Invalid appointment type.',
        ];
    }
}
