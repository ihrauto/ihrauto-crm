<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Merge two customer records into one.
 *
 * When duplicate customer records accumulate (e.g., the same person was
 * entered twice with slightly different names), this service consolidates
 * all related data onto a single surviving customer and soft-deletes the
 * other. Audit trail is preserved.
 *
 * SAFETY INVARIANTS:
 *   - Both customers must belong to the same tenant (enforced).
 *   - The primary (surviving) customer must not be the one being deleted.
 *   - Runs in a single DB transaction — merge either fully succeeds or fully
 *     rolls back. No partial state possible.
 *   - The duplicate is SOFT-deleted; all its data is transferred, not deleted.
 *   - Records soft-deleted by previous operations are included in the transfer
 *     (withTrashed) so nothing is lost.
 *
 * AUDIT:
 *   - An AuditLog entry records the merge with before/after IDs.
 *   - The duplicate customer's notes field is appended to the primary's notes
 *     so human-entered context isn't silently dropped.
 */
class CustomerMergeService
{
    /**
     * Merge $duplicate into $primary. Returns the primary customer with
     * all relationships refreshed.
     *
     * @throws \InvalidArgumentException if the customers can't be merged safely
     */
    public function merge(Customer $primary, Customer $duplicate): Customer
    {
        if ($primary->id === $duplicate->id) {
            throw new \InvalidArgumentException('Cannot merge a customer with itself.');
        }

        if ($primary->tenant_id !== $duplicate->tenant_id) {
            throw new \InvalidArgumentException('Cannot merge customers from different tenants.');
        }

        if (! $primary->tenant_id || ! $duplicate->tenant_id) {
            throw new \InvalidArgumentException('Both customers must belong to a tenant.');
        }

        return DB::transaction(function () use ($primary, $duplicate) {
            // Transfer all owned records. We use query builder with explicit
            // updates (not $duplicate->vehicles()->update()) to ensure trashed
            // records are also moved.
            $primaryId = $primary->id;
            $duplicateId = $duplicate->id;
            $tenantId = $primary->tenant_id;

            // Vehicles (include trashed so history survives)
            DB::table('vehicles')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $duplicateId)
                ->update(['customer_id' => $primaryId, 'updated_at' => now()]);

            // Checkins
            DB::table('checkins')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $duplicateId)
                ->update(['customer_id' => $primaryId, 'updated_at' => now()]);

            // Work orders
            DB::table('work_orders')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $duplicateId)
                ->update(['customer_id' => $primaryId, 'updated_at' => now()]);

            // Invoices — DB trigger prevents modifying locked invoices, but
            // customer_id is NOT a locked field, so this is safe.
            DB::table('invoices')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $duplicateId)
                ->update(['customer_id' => $primaryId, 'updated_at' => now()]);

            // Quotes
            DB::table('quotes')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $duplicateId)
                ->update(['customer_id' => $primaryId, 'updated_at' => now()]);

            // Tires
            DB::table('tires')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $duplicateId)
                ->update(['customer_id' => $primaryId, 'updated_at' => now()]);

            // Appointments
            DB::table('appointments')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $duplicateId)
                ->update(['customer_id' => $primaryId, 'updated_at' => now()]);

            // Preserve the duplicate's notes by appending them to the primary.
            if ($duplicate->notes && trim((string) $duplicate->notes) !== '') {
                $stamp = now()->format('Y-m-d H:i');
                $mergeMarker = "\n\n[Merged from customer #{$duplicateId} on {$stamp}]\n";
                $primary->notes = ($primary->notes ? $primary->notes : '').$mergeMarker.$duplicate->notes;
            }

            // Backfill contact fields from the duplicate when the primary is empty.
            // Phone, email, address are only filled if the primary doesn't already
            // have a value — we never overwrite existing primary data.
            $backfilledFields = [];
            foreach (['phone', 'email', 'address', 'city', 'postal_code'] as $field) {
                if (empty($primary->{$field}) && ! empty($duplicate->{$field})) {
                    $primary->{$field} = $duplicate->{$field};
                    $backfilledFields[] = $field;
                }
            }

            // If we're copying values that have a tenant-scoped unique constraint
            // (e.g. customers.email), we must clear them on the duplicate FIRST —
            // otherwise the primary save() hits a uniqueness violation. The
            // duplicate is about to be soft-deleted anyway.
            //
            // Unique-constrained columns (email) get NULL so they don't collide.
            // NOT-NULL columns (phone) get a tombstone value including the old
            // ID so they're still distinguishable from other records.
            if (! empty($backfilledFields)) {
                $clearValues = [];
                foreach ($backfilledFields as $field) {
                    if (in_array($field, ['email', 'address', 'city', 'postal_code'], true)) {
                        // Nullable column — use null for clean state
                        $clearValues[$field] = null;
                    } else {
                        // NOT NULL column (phone) — tombstone to keep it unique
                        $clearValues[$field] = '[merged:'.$duplicateId.']';
                    }
                }
                $duplicate->forceFill($clearValues)->save();
            }

            $primary->save();

            // Record audit trail before the soft delete.
            \App\Models\AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'action' => 'customer.merge',
                'model_type' => Customer::class,
                'model_id' => $primaryId,
                'changes' => [
                    'merged_from_id' => $duplicateId,
                    'merged_from_name' => $duplicate->name,
                    'merged_into_name' => $primary->name,
                ],
            ]);

            // Soft delete the duplicate. The Customer::booted() cascade will
            // soft-delete any REMAINING vehicles — but we just moved them all
            // to the primary, so there should be none left on the duplicate.
            $duplicate->delete();

            return $primary->fresh();
        });
    }
}
