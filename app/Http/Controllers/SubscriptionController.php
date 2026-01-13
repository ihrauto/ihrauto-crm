<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Show the checkout page for a specific tenant plan.
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
     */
    public function process(Request $request, $tenantId)
    {
        // Simulate backend processing
        $tenant = Tenant::findOrFail($tenantId);
        $plan = $request->input('plan', $tenant->plan);

        // 1. Simulate "Charge" (Mock)
        // In real app: actual Stripe/Gateway call

        // 2. Switch Context & Update Plan

        // Define limits based on plan
        $limits = match ($plan) {
            'basic' => [
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50,
            ],
            'standard' => [
                'max_users' => 5,
                'max_customers' => 1000,
                'max_vehicles' => 3000,
                'max_work_orders' => null,
            ],
            'custom' => [ // Should be handled by sales, but for safety
                'max_users' => 999999,
                'max_customers' => 999999,
                'max_vehicles' => 999999,
                'max_work_orders' => null,
            ],
            default => [
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50,
            ],
        };

        $tenant->update([
            'plan' => $plan,
            'is_trial' => false,
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addMonth(),
            'max_users' => $limits['max_users'],
            'max_customers' => $limits['max_customers'],
            'max_vehicles' => $limits['max_vehicles'],
            'max_work_orders' => $limits['max_work_orders'],
        ]);

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
        if (!tenant()) {
            return redirect()->route('dev.tenant-switch');
        }

        return view('subscription.onboarding', ['tenant' => tenant()]);
    }

    /**
     * Store company setup details.
     */
    public function storeSetup(Request $request)
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'No active tenant'], 404);
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'currency' => 'required|string|size:3',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'bank_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:50',
        ]);

        // Update core fields
        $tenant->update([
            'name' => $validated['company_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'], // Warning: ensure this doesn't conflict with unique constraint if changed to existing
            'address' => $validated['address'],
            'city' => $validated['city'],
            'currency' => $validated['currency'],
        ]);

        // Update settings JSON
        $settings = $tenant->settings ?? [];
        $settings['default_tax_rate'] = $validated['tax_rate'];
        $settings['bank_name'] = $validated['bank_name'];
        $settings['iban'] = $validated['iban'];

        // Mark tour as not seen yet (or ready to be seen)
        $settings['has_seen_tour'] = false;

        $tenant->update(['settings' => $settings]);

        // Clear cache so next request sees updated settings
        \Illuminate\Support\Facades\Cache::forget("tenant.id.{$tenant->id}");

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
            \Illuminate\Support\Facades\Cache::forget("tenant.id.{$tenant->id}");
        }

        return response()->json(['success' => true]);
    }
}
