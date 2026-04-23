<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\View\View;

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
}
