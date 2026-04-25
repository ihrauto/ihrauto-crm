<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Audit-M-9: defense-in-depth policy for Payment.
 *
 * Payment.php documents the model as append-only ("create reversing
 * negative payments to void") — the policy enforces that. Combined
 * with the controller's `permission:` middleware on the route, this
 * gives two independent layers: route permission (can-this-role-do-it)
 * + policy (can-this-user-touch-THIS-payment).
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('access finance');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->tenant_id === $payment->tenant_id
            && $user->can('access finance');
    }

    public function create(User $user): bool
    {
        return $user->can('access finance');
    }

    /**
     * Payments are append-only. The void workflow records a reversing
     * negative payment instead of mutating the original. Updating a
     * payment in place would defeat the immutability guarantee + the
     * audit trail it produces.
     */
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Same reasoning as update: deleting a payment would silently
     * change historical balances. Use the void / reversing-payment
     * flow instead.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
