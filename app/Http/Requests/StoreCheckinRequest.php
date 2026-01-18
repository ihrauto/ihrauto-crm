<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class StoreCheckinRequest extends FormRequest
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
        $rules = [
            'form_type' => 'required|in:active_user,new_customer',
            'priority' => 'required|in:low,medium,high,urgent',
            'service_bay' => 'required|string|max:50',
            'service_description' => 'nullable|string|max:1000',
        ];

        if ($this->form_type === 'active_user') {
            $rules = array_merge($rules, [
                'vehicle_id' => 'required|exists:vehicles,id',
                'service_type' => 'required|in:tire_change,oil_change,inspection,maintenance,repair,other',
            ]);
        } elseif ($this->form_type === 'new_customer') {
            // New customer validation
            $rules = array_merge($rules, [
                'customer_first_name' => 'required|string|max:255',
                'customer_last_name' => 'required|string|max:255',
                'phone' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^[\+]?[1-9][\d]{0,15}$/',
                ],
                'email' => 'nullable|email|max:255',
                'postal_code' => 'nullable|string|max:20',
                'city' => 'nullable|string|max:100',
                'street_address' => 'nullable|string|max:255',
                'license_plate' => [
                    'required',
                    'string',
                    'max:15',
                    function ($attribute, $value, $fail) {
                        // Normalize license plate (remove spaces, convert to uppercase)
                        $normalizedPlate = strtoupper(str_replace(' ', '', trim($value)));

                        // Check if any existing vehicle has this normalized plate
                        $existingVehicle = Vehicle::whereRaw("UPPER(REPLACE(license_plate, ' ', '')) = ?", [$normalizedPlate])->first();

                        if ($existingVehicle) {
                            $fail('A vehicle with license plate "' . $existingVehicle->license_plate . '" is already registered in our system.');
                        }
                    },
                ],
                'make' => 'required|string|max:50',
                'model' => 'required|string|max:50',
                'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'color' => 'nullable|string|max:30',
                'mileage' => 'nullable|integer|min:0',
                'services' => 'required|array|min:1',
                'services.*' => 'required|string',
            ]);
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'form_type.required' => 'Form type is required.',
            'form_type.in' => 'Invalid form type selected.',
            'vehicle_id.required' => 'Please select a vehicle.',
            'vehicle_id.exists' => 'Selected vehicle does not exist.',
            'service_type.required' => 'Please select a service type.',
            'service_type.in' => 'Invalid service type selected.',
            'priority.required' => 'Please select a priority level.',
            'priority.in' => 'Invalid priority level selected.',
            'service_bay.required' => 'Please select a service bay.',
            'customer_first_name.required' => 'First name is required.',
            'customer_last_name.required' => 'Last name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please enter a valid phone number.',
            'email.email' => 'Please enter a valid email address.',
            'license_plate.required' => 'License plate is required.',
            'make.required' => 'Vehicle make is required.',
            'model.required' => 'Vehicle model is required.',
            'year.required' => 'Vehicle year is required.',
            'year.integer' => 'Vehicle year must be a number.',
            'year.min' => 'Vehicle year must be 1900 or later.',
            'year.max' => 'Vehicle year cannot be in the future.',
            'mileage.integer' => 'Mileage must be a number.',
            'mileage.min' => 'Mileage cannot be negative.',
            'services.required' => 'Please select at least one service.',
            'services.min' => 'Please select at least one service.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'customer name',
            'license_plate' => 'license plate',
            'service_bay' => 'service bay',
            'service_type' => 'service type',
            'service_description' => 'service description',
        ];
    }
}
