<?php

namespace App\Rules;

use App\Services\WorkOrderService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * C-06: reusable validation rule replacing the `isTechnicianBusy()` check
 * duplicated across CheckinController, TireHotelController, and
 * WorkOrderController. Using it as a rule also produces consistent error
 * messages and removes the trait import from controllers.
 *
 * Usage:
 *     'technician_id' => ['nullable', new TechnicianAvailable($existingWorkOrderId ?? null)],
 */
class TechnicianAvailable implements ValidationRule
{
    public function __construct(
        private readonly ?int $excludeWorkOrderId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // nullable — let the nullable rule handle this
        }

        $busy = app(WorkOrderService::class)
            ->isTechnicianBusy((int) $value, $this->excludeWorkOrderId);

        if ($busy) {
            $fail('The selected technician is currently busy with another active job.');
        }
    }
}
