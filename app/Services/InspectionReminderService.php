<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ENG-012: TÜV / MFK / §57a inspection reminder driver.
 *
 * Reminder cadence:
 *   - 30 days before due date  → friendly heads-up
 *   - 14 days before due date  → second notice
 *   -  3 days before due date  → final reminder ("urgent")
 *
 * Idempotency:
 *   We persist the buckets we've already sent for the current
 *   `next_inspection_at` value into `vehicles.inspection_reminders_sent`
 *   (JSON array). When the operator updates `next_inspection_at`
 *   (e.g. after a passing inspection sets a new due date 1-2 years
 *   out), the buckets reset implicitly because we only check buckets
 *   for the CURRENT due date.
 *
 *   Cycle re-issue safety: the array is keyed by `bucket@iso_due_date`
 *   so changing the due date back-and-forth doesn't double-send.
 *
 * Reminder copy:
 *   Country-aware via Vehicle::inspectionAuthorityLabel(). Customer
 *   reads "TÜV", "MFK", or "§57a" — the legal name they recognize.
 */
class InspectionReminderService
{
    public const BUCKETS = [
        '30d' => 30,
        '14d' => 14,
        '3d' => 3,
    ];

    public function __construct(private readonly SmsService $sms) {}

    /**
     * For the given run-day (defaults to today), find every vehicle
     * whose next_inspection_at falls into one of the bucket windows
     * and the relevant bucket hasn't been sent yet for the current
     * due date. Send the SMS for each, log via CommunicationLog,
     * mark the bucket as sent, and return the per-vehicle result so
     * the command can print a summary.
     *
     * @return Collection<int, array{vehicle_id:int, bucket:string, status:string}>
     */
    public function sendDue(?Carbon $runDay = null): Collection
    {
        $today = ($runDay ?? Carbon::today())->startOfDay();
        $maxBucket = max(self::BUCKETS);

        // whereBetween with Carbon datetimes (start-of-day → end-of-day)
        // so the comparison works on whatever format the driver actually
        // stored. SQLite stores `Y-m-d H:i:s` for date casts; comparing
        // against bare date strings excludes 00:00:00 values lexicographically.
        $candidates = Vehicle::query()
            ->withoutGlobalScopes() // command runs cross-tenant
            ->whereNotNull('next_inspection_at')
            ->whereBetween('next_inspection_at', [
                $today->copy()->startOfDay(),
                $today->copy()->addDays($maxBucket)->endOfDay(),
            ])
            ->with(['customer', 'tenant'])
            ->get();

        $results = collect();

        foreach ($candidates as $vehicle) {
            $bucket = $this->resolveBucket($vehicle->next_inspection_at, $today);
            if ($bucket === null) {
                continue;
            }

            $sent = (array) ($vehicle->inspection_reminders_sent ?? []);
            $marker = $bucket.'@'.$vehicle->next_inspection_at->toDateString();
            if (in_array($marker, $sent, true)) {
                continue;
            }

            // Tenant context for the send. SmsService requires a tenant
            // on the model, which Vehicle has via tenant_id; we don't
            // need to push global tenant context because the service's
            // dispatch path resolves the tenant from $customer->tenant.
            $log = $this->sendForVehicle($vehicle, $bucket);

            // Always mark the bucket as attempted, even on failed/skipped
            // status — we don't want to spam-retry the same vehicle the
            // next day after a permanent failure (no phone, opt-out).
            // Operator can manually clear inspection_reminders_sent to
            // force a re-send.
            $sent[] = $marker;
            $vehicle->forceFill(['inspection_reminders_sent' => $sent])->save();

            $results->push([
                'vehicle_id' => $vehicle->id,
                'bucket' => $bucket,
                'status' => $log->status,
            ]);
        }

        return $results;
    }

    /**
     * Return the bucket key whose window the given due-date falls in,
     * or null if the date is outside every window. Bucket boundaries
     * are inclusive at the bucket day itself — e.g. 30 days before
     * the due date counts as "30d".
     */
    public function resolveBucket(Carbon $dueDate, Carbon $today): ?string
    {
        $daysOut = (int) $today->copy()->startOfDay()->diffInDays(
            $dueDate->copy()->startOfDay(),
            absolute: false,
        );
        if ($daysOut < 0) {
            return null; // already overdue — caller decides what to do
        }

        // Match the smallest bucket that's >= daysOut. So a vehicle
        // 28 days out lands in the 14d bucket on its 14th day, not the
        // 30d bucket — we always wait until the user is in-window.
        // Buckets in descending order to assign the bucket the
        // vehicle MOST RECENTLY entered:
        foreach (self::BUCKETS as $key => $days) {
            if ($daysOut === $days) {
                return $key;
            }
        }

        return null;
    }

    private function sendForVehicle(Vehicle $vehicle, string $bucket): CommunicationLog
    {
        $authority = $vehicle->inspectionAuthorityLabel();
        $plate = $vehicle->license_plate ?? 'your vehicle';
        $dueDate = $vehicle->next_inspection_at->format('d.m.Y');
        $shop = $vehicle->tenant?->name ?? 'the workshop';

        $intro = match ($bucket) {
            '30d' => "Friendly reminder: {$authority} for {$plate} is due {$dueDate}.",
            '14d' => "{$authority} for {$plate} is due {$dueDate} (14 days).",
            '3d' => "Last reminder — {$authority} for {$plate} is due {$dueDate}.",
            default => "{$authority} for {$plate} is due {$dueDate}.",
        };

        $body = "{$intro} Book your slot at {$shop} to keep your car road-legal.";

        return $this->sms->dispatch(
            customer: $vehicle->customer,
            workOrder: null,
            template: "inspection.reminder.{$bucket}",
            body: $body,
            tenant: $vehicle->tenant,
        );
    }
}
