<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    /**
     * Show the checkout page for a specific tenant plan.
     *
     * Local-only mock flow. Production billing is handled manually through
     * the tenant-facing pricing page and super-admin billing controls.
     */
    public function checkout($tenantId)
    {
        // In a real app, you'd verify if the user can switch to this tenant
        // For dev switching, we just grab the tenant data
        $tenant = Tenant::findOrFail($tenantId);

        // Define plan details for display (mirroring the switcher logic)
        $plans = [
            'basic' => [
                'id' => 'basic',
                'name' => 'Basic',
                'price' => '€49',
                'bill' => '€49.00',
                'price_yearly' => '€470',
                'bill_yearly' => '€470.00',
            ],
            'standard' => [
                'id' => 'standard',
                'name' => 'Standard',
                'price' => '€149',
                'bill' => '€149.00',
                'price_yearly' => '€1,430',
                'bill_yearly' => '€1,430.00',
            ],
            'custom' => [
                'id' => 'custom',
                'name' => 'Custom',
                'price' => 'Contact',
                'bill' => 'Contact Sales',
                'price_yearly' => 'Contact',
                'bill_yearly' => 'Contact Sales',
            ],
        ];

        // Allow overriding the plan (e.g. for upgrades)
        $targetPlan = request('plan', $tenant->plan);
        $planDetail = $plans[$targetPlan] ?? $plans['basic'];

        return view('subscription.checkout', compact('tenant', 'planDetail'));
    }

    /**
     * Process the mock payment.
     *
     * This endpoint is intentionally local-only and should never be used for
     * production billing.
     */
    public function process(Request $request, $tenantId)
    {
        // Audit-C-27: defense-in-depth — even if the route registration
        // ever escapes the local-only block in routes/web.php, refuse to
        // run outside local. This endpoint flips a tenant's plan with no
        // policy check; the local-only contract is load-bearing.
        abort_unless(app()->environment('local'), 404);

        // Simulate backend processing
        $tenant = Tenant::findOrFail($tenantId);
        $plan = $request->input('plan', $tenant->plan);

        // 1. Simulate "Charge" (Mock)
        // In real app: actual Stripe/Gateway call

        // 2. Switch Context & Update Plan

        $tenant->convertToSubscription($plan, now()->addMonth()->endOfDay());

        session(['tenant_id' => $tenant->id]);

        // 3. Redirect to Onboarding
        return response()->json([
            'success' => true,
            'redirect_url' => route('subscription.onboarding'),
        ]);
    }

    /**
     * Show the onboarding wizard.
     */
    public function onboarding()
    {
        // Ensure we have a tenant selected
        if (! tenant()) {
            return redirect()->route('home')
                ->with('error', 'Please select a plan to continue.');
        }

        return view('subscription.onboarding', ['tenant' => tenant()]);
    }

    /**
     * Store company setup details.
     */
    public function storeSetup(Request $request)
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json(['error' => 'No active tenant'], 404);
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('tenants', 'email')->ignore($tenant->id)],
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'currency' => 'required|string|size:3',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'bank_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:50',
        ]);

        // Build a PATCH-style update: only touch the tenant columns the
        // caller actually submitted. Nullable validation rules do not
        // populate $validated with the missing keys, so accessing them
        // directly used to crash with "Undefined array key". The
        // previous version also happily wrote NULL over NOT NULL
        // columns (e.g. tenants.email).
        $update = ['name' => $validated['company_name']];
        foreach (['phone', 'email', 'address', 'city'] as $column) {
            if (array_key_exists($column, $validated) && $validated[$column] !== null) {
                $update[$column] = $validated[$column];
            }
        }
        $update['currency'] = $validated['currency'];

        $tenant->update($update);

        // Merge settings JSON. Keep existing values when the caller
        // doesn't supply the nullable fields.
        $settings = $tenant->settings ?? [];
        $settings['default_tax_rate'] = $validated['tax_rate'];
        if (array_key_exists('bank_name', $validated) && $validated['bank_name'] !== null) {
            $settings['bank_name'] = $validated['bank_name'];
        }
        if (array_key_exists('iban', $validated) && $validated['iban'] !== null) {
            $settings['iban'] = $validated['iban'];
        }

        // Mark tour as not seen yet (or ready to be seen)
        $settings['has_seen_tour'] = false;

        $tenant->update(['settings' => $settings]);

        // Clear cache so next request sees updated settings
        TenantCache::forgetTenant($tenant);

        // Track onboarding completion event
        app(\App\Services\EventTracker::class)->track('onboarding_completed', $tenant->id, auth()->id());

        return response()->json(['success' => true]);
    }

    /**
     * Mark dashboard tour as complete.
     */
    public function markTourComplete()
    {
        $tenant = tenant();
        if ($tenant) {
            $settings = $tenant->settings ?? [];
            $settings['has_seen_tour'] = true;
            $tenant->update(['settings' => $settings]);

            // Clear cache
            TenantCache::forgetTenant($tenant);
        }

        return response()->json(['success' => true]);
    }
}
