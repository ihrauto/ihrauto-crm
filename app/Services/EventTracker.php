<?php

namespace App\Services;

use App\Models\Event;

class EventTracker
{
    /**
     * Track an event in the events table.
     *
     * @param string $event Event name (e.g., 'checkin_created', 'invoice_issued')
     * @param int|null $tenantId Tenant ID (optional, will use current tenant if null)
     * @param int|null $userId User ID (optional, will use current user if null)
     * @param array $meta Non-sensitive metadata (NO PII: names, emails, plates, invoices)
     */
    public function track(string $event, ?int $tenantId = null, ?int $userId = null, array $meta = []): Event
    {
        // Default to current authenticated context if not provided
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        $userId = $userId ?? auth()->id();

        return Event::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event' => $event,
            'meta' => empty($meta) ? null : $meta,
        ]);
    }

    /**
     * Track with just event name (uses current auth context).
     */
    public function trackSimple(string $event): Event
    {
        return $this->track($event);
    }
}
