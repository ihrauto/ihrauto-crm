<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTenantSettingsRequest;
use App\Support\TenantCache;

/**
 * Tenant-owner settings surface. Reached from the top-level management
 * dashboard via the "Settings" tab. Route group in routes/web.php wraps
 * every mutating action in `permission:manage settings`.
 */
class SettingsController extends Controller
{
    public function show()
    {
        return view('management.settings');
    }

    public function update(UpdateTenantSettingsRequest $request)
    {
        // Defense-in-depth: routes already guard with permission:manage
        // settings, but the legacy `perform-admin-actions` gate used to
        // live here — keep it so a future route-move can't silently
        // widen access.
        \Illuminate\Support\Facades\Gate::authorize('perform-admin-actions');

        $validated = $request->validated();

        $tenant = auth()->user()->tenant;

        $tenant->update([
            'name' => $validated['company_name'],
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'uid_number' => $validated['uid_number'],
            'vat_registered' => $request->has('vat_registered') && $request->vat_registered == '1',
            'vat_number' => $validated['vat_number'],
            'bank_name' => $validated['bank_name'],
            'iban' => $validated['iban'],
            'account_holder' => $validated['account_holder'],
            'invoice_email' => $validated['invoice_email'],
            'invoice_phone' => $validated['invoice_phone'],
            'currency' => $validated['currency'],
        ]);

        // Feature toggles live in tenants.features; helper to mutate.
        $features = $tenant->features ?? [];
        $updateFeature = function ($featureKey, $isEnabled) use (&$features) {
            if ($isEnabled && ! in_array($featureKey, $features)) {
                $features[] = $featureKey;
            } elseif (! $isEnabled && in_array($featureKey, $features)) {
                $features = array_diff($features, [$featureKey]);
            }
        };
        $updateFeature('tire_hotel', $request->has('module_tire_hotel'));
        $updateFeature('vehicle_checkin', $request->has('module_checkin'));
        $tenant->features = array_values($features);

        // Settings JSON for the rest.
        $settings = $tenant->settings ?? [];
        $settings['tax_rate'] = $validated['tax_rate'];
        if (! empty($validated['invoice_prefix'])) {
            $settings['invoice_prefix'] = strtoupper($validated['invoice_prefix']);
        }
        if (array_key_exists('default_due_days', $validated) && $validated['default_due_days'] !== null) {
            $settings['default_due_days'] = (int) $validated['default_due_days'];
        }
        // Store 0 rather than null for "disabled" so the scheduler's
        // short-circuit check works the same whether the key was never
        // saved or was saved as zero.
        $settings['auto_issue_drafts_after_days'] = (int) ($validated['auto_issue_drafts_after_days'] ?? 0);
        $settings['low_stock_email'] = $request->boolean('low_stock_email');
        $tenant->settings = $settings;

        $tenant->save();
        TenantCache::forgetTenant($tenant);

        return redirect()->route('management.settings')->with('success', 'Company information and settings updated successfully.');
    }
}
