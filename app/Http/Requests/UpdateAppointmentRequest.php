<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Support\TenantValidation;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Full update (has start_date + start_time)
        if ($this->has('start_date') && $this->has('start_time')) {
            return [
                'customer_id' => ['required', TenantValidation::exists('customers')],
                'start_date' => 'required|date',
                'start_time' => 'required',
                'duration' => 'required|integer|min:15|max:480',
                'type' => 'required|string|in:tire_change,oil_change,inspection,maintenance,repair,other',
                'notes' => 'nullable|string|max:2000',
            ];
        }

        // Status-only update
        return [
            'status' => 'required|in:scheduled,confirmed,completed,failed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Additional validation after standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->has('start_date')) {
                return;
            }

            $this->checkForConflicts($validator);
        });
    }

    /**
     * Check for overlapping appointments on the same vehicle (excluding this appointment).
     */
    protected function checkForConflicts($validator): void
    {
        $appointment = $this->route('appointment');
        $vehicleId = $appointment?->vehicle_id;
        if (! $vehicleId) {
            return;
        }

        $startDateTime = Carbon::parse($this->start_date.' '.$this->start_time);
        $endDateTime = $startDateTime->copy()->addMinutes((int) $this->duration);

        $conflict = Appointment::where('vehicle_id', $vehicleId)
            ->where('id', '!=', $appointment->id)
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
}
