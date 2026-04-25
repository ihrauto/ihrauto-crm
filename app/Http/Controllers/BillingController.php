<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * ENG-010: Stripe Checkout + Customer Portal entry points.
 *
 * Replaces the local-only mock in SubscriptionController. Reads the
 * plan→price-ID map from config('services.stripe.prices') so each
 * environment (local / test / prod) can point at its own Stripe products.
 *
 * Flow:
 *   tenant admin clicks "Upgrade" → checkout($plan)
 *     → Cashier creates a Stripe Checkout session
 *     → user pays on Stripe-hosted page
 *     → Stripe redirects to success() (with session_id query param)
 *     → tenant.stripe_id + subscription rows are populated by the
 *       webhook handler asynchronously; we don't trust the redirect
 *       to confirm payment
 *
 * Self-service:
 *   tenant admin clicks "Manage subscription" → portal()
 *     → Cashier creates a Billing Portal session and redirects there.
 *     → portal handles plan changes, card updates, cancellations,
 *       invoice history, dunning self-recovery — Stripe owns the UI.
 */
class BillingController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $tenant = $user?->tenant;

        abort_if(! $tenant, 403, 'No tenant associated with this account.');

        $planCatalog = Tenant::planCatalog();
        $currentPlanKey = $tenant->plan;
        $currentPlan = Tenant::planDefinition($currentPlanKey);
        $renewalDate = $tenant->is_trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at;

        return view('billing.pricing', compact('tenant', 'planCatalog', 'currentPlanKey', 'currentPlan', 'renewalDate'));
    }

    /**
     * Create a Stripe Checkout session for the given plan and redirect.
     * The custom plan is sales-led (no Stripe price), so we redirect
     * back with an info banner instead of 404ing the user.
     */
    public function checkout(Request $request, string $plan): RedirectResponse|Response
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return redirect()->route('home')->with('error', 'No active tenant context.');
        }

        $priceId = config("services.stripe.prices.{$plan}");
        if (! $priceId) {
            return redirect()->route('billing.pricing')
                ->with('error', "Plan '{$plan}' is sales-led — please contact us.");
        }

        try {
            $checkout = $tenant->newSubscription('default', $priceId)
                ->trialDays(config('services.stripe.trial_days', 14))
                ->checkout([
                    'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('billing.cancel'),
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'plan_key' => $plan,
                    ],
                ]);

            return redirect($checkout->url);
        } catch (\Throwable $e) {
            Log::error('billing_checkout_failed', [
                'tenant_id' => $tenant->id,
                'plan' => $plan,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('billing.pricing')
                ->with('error', 'Could not start checkout. Please try again or contact support.');
        }
    }

    /**
     * Stripe redirects here after a successful checkout. We DO NOT
     * trust this redirect as proof of payment — the webhook does that.
     * Just show a "thanks, we're processing" page while the webhook lands.
     */
    public function success(Request $request): View
    {
        return view('billing.success', [
            'tenant' => $this->resolveTenant($request),
            'sessionId' => $request->query('session_id'),
        ]);
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->route('billing.pricing')
            ->with('info', 'Checkout was cancelled. No charge was made.');
    }

    /**
     * Hand off to Stripe Customer Portal — plan changes, card updates,
     * invoices, cancellation, dunning self-recovery all live there.
     */
    public function portal(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant || ! $tenant->stripe_id) {
            return redirect()->route('billing.pricing')
                ->with('info', 'Set up a paid plan first to access the billing portal.');
        }

        try {
            return $tenant->redirectToBillingPortal(route('billing.pricing'));
        } catch (\Throwable $e) {
            Log::error('billing_portal_failed', [
                'tenant_id' => $tenant->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('billing.pricing')
                ->with('error', 'Could not open billing portal. Please try again.');
        }
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        return $request->user()?->tenant;
    }
}
