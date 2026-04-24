<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Calendar drag-and-drop reschedule. The caller sends two ISO timestamps,
 * `start` and optional `end`; the controller preserves the original
 * appointment duration when `end` is missing.
 *
 * Authorization is already handled by a policy call
 * (`$this->authorize('update', $appointment)`) in AppointmentController.
 */
class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }
}
