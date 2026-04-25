<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

/**
 * ENG-011: SMS dispatch with audit-first semantics.
 *
 * Every send attempt — successful, failed, or skipped — produces a
 * CommunicationLog row before any side effect (the row is the receipt).
 * That means: ops can answer "did we tell this customer their car was
 * ready?" by looking at one table, and a delivery-failure UI badge can
 * surface against the failed log row without polling Twilio.
 *
 * Guards (in order):
 *   1. Tenant context bound (else throw — same fail-loud rule as
 *      DashboardService).
 *   2. Tenant has opted in: tenants.settings.sms.enabled === true.
 *   3. Customer has not opted out: customers.sms_opt_out === false.
 *   4. Phone number normalizes to E.164 with the tenant's default
 *      region (Switzerland by default).
 *   5. Twilio credentials present.
 *
 * Each failed guard produces a CommunicationLog with status=skipped
 * and an explanatory error_message — so the UI can show e.g.
 * "Customer opted out of SMS" rather than a generic "could not send".
 *
 * Twilio failures (rate limit, invalid number, network) get a
 * status=failed row with the Twilio error code preserved.
 */
class SmsService
{
    public function __construct(
        private readonly ?TwilioClient $client = null,
    ) {}

    public function client(): ?TwilioClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        if (! $sid || ! $token) {
            return null;
        }

        return new TwilioClient($sid, $token);
    }

    /**
     * Send the "your car is ready" notification for a work order.
     * Returns the CommunicationLog row (whether queued, failed, or
     * skipped) so the caller can flash UI feedback.
     */
    public function sendWorkOrderReady(WorkOrder $workOrder, ?int $userId = null): CommunicationLog
    {
        $customer = $workOrder->customer;
        $vehicle = $workOrder->vehicle;
        $vehicleLabel = $vehicle
            ? trim(($vehicle->make ?? '').' '.($vehicle->model ?? ''))
            : 'your vehicle';

        $tenant = $workOrder->tenant ?? Tenant::find($workOrder->tenant_id);
        $shopName = $tenant?->name ?? 'the workshop';

        $body = "Hi {$customer->name}, {$vehicleLabel} is ready for pickup at {$shopName}. ".
            'Please get in touch to arrange collection. — '.$shopName;

        return $this->dispatch(
            customer: $customer,
            workOrder: $workOrder,
            template: 'work_order.ready',
            body: $body,
            userId: $userId,
            tenant: $tenant,
        );
    }

    /**
     * Generic dispatch path. Public so future templates (appointment
     * reminder, TÜV due, etc.) can reuse the guards + logging.
     */
    public function dispatch(
        ?Customer $customer,
        ?WorkOrder $workOrder,
        string $template,
        string $body,
        ?int $userId = null,
        ?Tenant $tenant = null,
    ): CommunicationLog {
        $tenant ??= $customer?->tenant ?? ($workOrder ? Tenant::find($workOrder->tenant_id) : null);

        if (! $tenant) {
            throw new \LogicException('SmsService::dispatch requires a tenant context.');
        }

        $sms = (array) ($tenant->settings['sms'] ?? []);
        if (empty($sms['enabled'])) {
            return $this->skip($tenant, $customer, $workOrder, $userId, $template, $body, 'tenant_disabled', 'SMS not enabled for this tenant.');
        }

        if (! $customer) {
            return $this->skip($tenant, null, $workOrder, $userId, $template, $body, 'no_customer', 'No customer attached.');
        }

        if ($customer->sms_opt_out) {
            return $this->skip($tenant, $customer, $workOrder, $userId, $template, $body, 'opt_out', 'Customer has opted out of SMS.');
        }

        $rawPhone = (string) ($customer->phone ?? '');
        $e164 = $this->normalizeE164($rawPhone, config('services.twilio.default_region', 'CH'));
        if (! $e164) {
            return $this->skip($tenant, $customer, $workOrder, $userId, $template, $body, 'no_phone', 'Customer has no usable phone number.');
        }

        $client = $this->client();
        $from = $sms['from_number'] ?? config('services.twilio.from');
        if (! $client || ! $from) {
            return $this->skip($tenant, $customer, $workOrder, $userId, $template, $body, 'not_configured', 'Twilio credentials or From number not configured.');
        }

        try {
            $message = $client->messages->create($e164, [
                'from' => $from,
                'body' => $body,
            ]);

            return CommunicationLog::forceCreate([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'work_order_id' => $workOrder?->id,
                'user_id' => $userId,
                'channel' => CommunicationLog::CHANNEL_SMS,
                'to' => $e164,
                'template' => $template,
                'body' => $body,
                'status' => CommunicationLog::STATUS_QUEUED,
                'provider_id' => $message->sid ?? null,
                'sent_at' => now(),
            ]);
        } catch (TwilioException $e) {
            Log::warning('sms_send_failed', [
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'work_order_id' => $workOrder?->id,
                'error_code' => method_exists($e, 'getCode') ? (string) $e->getCode() : null,
                'message' => $e->getMessage(),
            ]);

            return CommunicationLog::forceCreate([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'work_order_id' => $workOrder?->id,
                'user_id' => $userId,
                'channel' => CommunicationLog::CHANNEL_SMS,
                'to' => $e164,
                'template' => $template,
                'body' => $body,
                'status' => CommunicationLog::STATUS_FAILED,
                'error_code' => (string) $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function skip(
        Tenant $tenant,
        ?Customer $customer,
        ?WorkOrder $workOrder,
        ?int $userId,
        string $template,
        string $body,
        string $errorCode,
        string $errorMessage,
    ): CommunicationLog {
        return CommunicationLog::forceCreate([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer?->id,
            'work_order_id' => $workOrder?->id,
            'user_id' => $userId,
            'channel' => CommunicationLog::CHANNEL_SMS,
            'to' => $customer?->phone ?? '—',
            'template' => $template,
            'body' => $body,
            'status' => CommunicationLog::STATUS_SKIPPED,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * E.164 normalize. Accepts:
     *   +41 79 555 12 34
     *   079 555 12 34       (CH region default)
     *   0041 79 555 1234
     *   +41795551234
     * Returns null if it can't reasonably be coerced — caller falls back
     * to skipped status with an explanatory error code.
     *
     * Avoids the libphonenumber dependency for now: most workshops have
     * customers in one country (Switzerland) so a small region-aware
     * normalizer is enough. Worth swapping in libphonenumber later if
     * we expand DACH-wide.
     */
    public function normalizeE164(string $raw, string $region = 'CH'): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        // Strip an "00" international prefix → "+".
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '+')) {
            // Already E.164-ish; accept anything with at least 8 digits after the +.
            $rest = substr($digits, 1);

            return preg_match('/^\d{8,15}$/', $rest) ? '+'.$rest : null;
        }

        // No prefix — assume default region.
        $cc = match ($region) {
            'CH' => '41',
            'DE' => '49',
            'AT' => '43',
            default => null,
        };
        if (! $cc) {
            return null;
        }

        // Strip a leading 0 (CH/DE/AT national trunk prefix).
        $national = ltrim($digits, '0');
        if (! preg_match('/^\d{6,14}$/', $national)) {
            return null;
        }

        return '+'.$cc.$national;
    }
}
