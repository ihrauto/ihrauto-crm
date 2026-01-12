<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return true; // Tenant scope handles filtering
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->tenant_id === $invoice->tenant_id;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the invoice.
     * Only draft invoices can be updated.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if ($user->tenant_id !== $invoice->tenant_id) {
            return false;
        }

        // Only draft invoices can be updated
        return $invoice->isEditable();
    }

    /**
     * Determine whether the user can delete the invoice.
     * Issued invoices cannot be deleted.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        if ($user->tenant_id !== $invoice->tenant_id) {
            return false;
        }

        // Only draft invoices can be deleted
        return $invoice->isDraft();
    }

    /**
     * Determine whether the user can issue (finalize) the invoice.
     */
    public function issue(User $user, Invoice $invoice): bool
    {
        if ($user->tenant_id !== $invoice->tenant_id) {
            return false;
        }

        return $invoice->isDraft();
    }

    /**
     * Determine whether the user can void the invoice.
     */
    public function void(User $user, Invoice $invoice): bool
    {
        if ($user->tenant_id !== $invoice->tenant_id) {
            return false;
        }

        return $invoice->canBeVoided();
    }

    /**
     * Determine whether the user can mark the invoice as paid.
     */
    public function markPaid(User $user, Invoice $invoice): bool
    {
        if ($user->tenant_id !== $invoice->tenant_id) {
            return false;
        }

        return $invoice->isIssued() && ! $invoice->isPaid();
    }

    /**
     * Determine whether the user can restore the invoice.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->tenant_id === $invoice->tenant_id && $invoice->isDraft();
    }

    /**
     * Determine whether the user can permanently delete the invoice.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false; // Never allow permanent deletion
    }
}
