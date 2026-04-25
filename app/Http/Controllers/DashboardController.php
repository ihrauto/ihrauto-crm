<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\DashboardStudioService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected DashboardStudioService $studio,
    ) {}

    public function index(Request $request)
    {
        // Redirect super-admin to admin dashboard
        try {
            if (auth()->user()?->hasRole('super-admin')) {
                return redirect()->route('admin.dashboard');
            }
        } catch (\Exception $e) {
            // Roles not set up yet - continue as regular user
        }

        return view('dashboard', $this->buildContext($request->user()));
    }

    /**
     * Returns just the widgets-grid HTML for in-place swap after a Studio
     * panel save. The panel calls this so the user keeps toggling without
     * losing the open panel to a full page reload.
     */
    public function widgetsFragment(Request $request)
    {
        return view('dashboard._widgets', $this->buildContext($request->user()));
    }

    /**
     * Compute the variables every dashboard render needs. Only widgets the
     * user has enabled get their data provider called; two widgets sharing
     * a provider trigger only one call (request-scoped memo).
     *
     * Returns:
     *   - enabledWidgets: catalog entries to render
     *   - stats / todays_schedule / technician_status: legacy named keys
     *     that the older partials read directly. New list-type partials
     *     read providerData[$widget_key] instead so they don't need the
     *     controller to invent a new variable name per widget.
     *
     * @return array<string, mixed>
     */
    private function buildContext($user): array
    {
        $enabledWidgets = $this->studio->enabledWidgetsForUser($user);

        $providerCache = [];
        $providerData = [];
        foreach ($enabledWidgets as $widget) {
            $provider = $widget['data_provider'] ?? null;
            if ($provider === null) {
                continue;
            }
            if (! array_key_exists($provider, $providerCache)) {
                $providerCache[$provider] = $this->dashboardService->{$provider}();
            }
            $providerData[$widget['key']] = $providerCache[$provider];
        }

        return [
            'enabledWidgets' => $enabledWidgets,
            'stats' => $providerCache['getStats'] ?? [],
            'todays_schedule' => $providerCache['getTodaysSchedule'] ?? [],
            'technician_status' => $providerCache['getTechnicianStatus'] ?? [],
            'providerData' => $providerData,
        ];
    }
}
