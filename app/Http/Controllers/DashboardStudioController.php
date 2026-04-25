<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderDashboardStudioRequest;
use App\Http\Requests\StoreDashboardStudioRequest;
use App\Services\DashboardStudioService;
use App\Support\DashboardWidgetCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ENG-009: Dashboard Studio HTTP surface.
 *
 * JSON-only. The Studio panel is rendered by Blade + Alpine on the
 * dashboard page itself; this controller exists to read/write the
 * user's stored preferences without a full page reload (POSTs are
 * followed by a window.location.reload() in v1 — Phase 2 swaps it for
 * targeted re-render).
 */
class DashboardStudioController extends Controller
{
    public function __construct(private readonly DashboardStudioService $studio) {}

    /**
     * Return the catalog filtered by what this user can see, plus the
     * keys currently enabled. Used for warm hydration of an open panel
     * if it ever needs to re-fetch (e.g. after a focus event).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'categories' => DashboardWidgetCatalog::categories(),
            'widgets' => $this->studio->widgetsForUser($user),
            'enabled' => $this->studio->enabledKeysForUser($user),
        ]);
    }

    public function store(StoreDashboardStudioRequest $request): JsonResponse
    {
        $effective = $this->studio->setEnabled(
            $request->user(),
            $request->input('keys', [])
        );

        return response()->json([
            'enabled' => $effective,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $effective = $this->studio->resetToDefault($request->user());

        return response()->json([
            'enabled' => $effective,
        ]);
    }

    public function reorder(ReorderDashboardStudioRequest $request): JsonResponse
    {
        $order = $this->studio->setOrder(
            $request->user(),
            $request->input('order', [])
        );

        return response()->json(['order' => $order]);
    }
}
