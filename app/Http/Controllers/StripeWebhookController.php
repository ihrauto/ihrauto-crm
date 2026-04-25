<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * ENG-010: Stripe webhook handler.
 *
 * Two things this gets right that most app implementations miss:
 *
 *   1. Signature verification. Reject any request whose
 *      `Stripe-Signature` header doesn't match the webhook secret.
 *      Without this, anyone who knows the URL can POST a forged
 *      `customer.subscription.created` and flip a tenant onto a
 *      paid plan for free.
 *
 *   2. Idempotency. Stripe will redeliver events on transport errors
 *      (a 5xx, a network blip, a timeout). We persist each event_id
 *      in the cache for 24h after first processing. A redelivery is
 *      a no-op — no double-billing-state, no duplicate audit rows.
 *
 * Events we care about:
 *   - customer.subscription.created  → tenant becomes active on the plan
 *   - customer.subscription.updated  → plan change, status change
 *   - customer.subscription.deleted  → access ends at period end
 *   - invoice.payment_succeeded      → clear past_due flag, refresh renewal
 *   - invoice.payment_failed         → flip tenant to past_due (dunning banner)
 *
 * Cashier ships its own WebhookController that updates the
 * subscription rows automatically — we override `handle()` to:
 *     a) verify the signature
 *     b) gate on idempotency
 *     c) defer to Cashier's handler for sub state, then run our
 *        platform-side hooks (is_active, plan, dunning flag, etc.)
 */
class StripeWebhookController extends \Laravel\Cashier\Http\Controllers\WebhookController
{
    public function handleWebhook(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook.secret');
        $tolerance = (int) config('services.stripe.webhook.tolerance', 300);
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        if (empty($secret)) {
            // Defensive: refuse to run if the env wasn't configured. A
            // missing secret means signature verification is impossible
            // — better to 503 loudly than silently process forged events.
            Log::error('stripe_webhook_secret_missing');

            return response()->json(['error' => 'Webhook secret not configured.'], 503);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret, $tolerance);
        } catch (SignatureVerificationException $e) {
            Log::warning('stripe_webhook_invalid_signature', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature.'], 403);
        } catch (\Throwable $e) {
            Log::warning('stripe_webhook_payload_invalid', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid payload.'], 400);
        }

        // Idempotency. Stripe redelivers on transport failure; the
        // event id is the natural dedupe key. 24h is well past Stripe's
        // retry envelope (max ~3 days) — long enough that legitimate
        // re-deliveries are caught, short enough that we don't fill
        // cache forever.
        $idempotencyKey = "stripe_webhook_handled:{$event->id}";
        if (Cache::has($idempotencyKey)) {
            return response()->json(['status' => 'duplicate']);
        }
        Cache::put($idempotencyKey, true, now()->addDay());

        // Dispatch by event type. Cashier handles the subscription rows
        // for us (it ships built-in handlers for created/updated/deleted)
        // — we layer the platform-side state on top via the methods
        // below. The parent class auto-routes to handle{EventType}.
        $method = 'handle'.str_replace([
            'customer.subscription.', 'invoice.', '.',
        ], [
            'CustomerSubscription', 'Invoice', '',
        ], $event->type);

        if (method_exists($this, $method)) {
            return $this->{$method}($event->toArray());
        }

        return $this->missingMethod();
    }

    /**
     * customer.subscription.created
     */
    public function handleCustomerSubscriptionCreated(array $payload): JsonResponse
    {
        parent::handleCustomerSubscriptionCreated($payload);

        $tenant = $this->resolveTenant($payload);
        if (! $tenant) {
            return response()->json(['status' => 'tenant_not_found']);
        }

        $tenant->forceFill([
            'is_active' => true,
            'is_trial' => false,
            'plan' => $this->planFromPriceId($payload['data']['object']['items']['data'][0]['price']['id'] ?? null) ?? $tenant->plan,
            'subscription_ends_at' => $this->endsAt($payload),
        ])->save();

        return response()->json(['status' => 'ok']);
    }

    public function handleCustomerSubscriptionUpdated(array $payload): JsonResponse
    {
        parent::handleCustomerSubscriptionUpdated($payload);

        $tenant = $this->resolveTenant($payload);
        if (! $tenant) {
            return response()->json(['status' => 'tenant_not_found']);
        }

        $status = $payload['data']['object']['status'] ?? null;

        $update = [
            'plan' => $this->planFromPriceId($payload['data']['object']['items']['data'][0]['price']['id'] ?? null) ?? $tenant->plan,
            'subscription_ends_at' => $this->endsAt($payload),
        ];

        if ($status === 'active' || $status === 'trialing') {
            $update['is_active'] = true;
        }
        if ($status === 'canceled' || $status === 'incomplete_expired') {
            $update['is_active'] = false;
        }

        $tenant->forceFill($update)->save();

        return response()->json(['status' => 'ok']);
    }

    public function handleCustomerSubscriptionDeleted(array $payload): JsonResponse
    {
        parent::handleCustomerSubscriptionDeleted($payload);

        $tenant = $this->resolveTenant($payload);
        if ($tenant) {
            $tenant->forceFill([
                'is_active' => false,
                'subscription_ends_at' => now(),
            ])->save();
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Dunning: invoice.payment_failed → mark tenant past_due so the
     * UI can surface the recovery banner. Stripe will keep retrying
     * the card per the dunning schedule; the tenant can self-fix via
     * the Customer Portal.
     */
    public function handleInvoicePaymentFailed(array $payload): JsonResponse
    {
        $tenant = $this->resolveTenant($payload);
        if ($tenant) {
            // Don't flip is_active=false here — Stripe will retry. We
            // only show the banner so the operator notices.
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $settings['billing_status'] = 'past_due';
            $settings['billing_status_set_at'] = now()->toIso8601String();
            $tenant->forceFill(['settings' => $settings])->save();
        }

        return response()->json(['status' => 'ok']);
    }

    public function handleInvoicePaymentSucceeded(array $payload): JsonResponse
    {
        $tenant = $this->resolveTenant($payload);
        if ($tenant) {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            unset($settings['billing_status'], $settings['billing_status_set_at']);
            $tenant->forceFill([
                'settings' => $settings,
                'is_active' => true,
            ])->save();
        }

        return response()->json(['status' => 'ok']);
    }

    private function resolveTenant(array $payload): ?Tenant
    {
        $customerId = $payload['data']['object']['customer']
            ?? $payload['data']['object']['stripe_customer_id']
            ?? null;
        if (! $customerId) {
            return null;
        }

        return Tenant::withoutGlobalScopes()
            ->where('stripe_id', $customerId)
            ->first();
    }

    private function planFromPriceId(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }
        $map = (array) config('services.stripe.prices', []);

        return array_search($priceId, $map, true) ?: null;
    }

    private function endsAt(array $payload): ?\Illuminate\Support\Carbon
    {
        $ts = $payload['data']['object']['current_period_end'] ?? null;

        return $ts ? \Illuminate\Support\Carbon::createFromTimestamp($ts) : null;
    }
}
