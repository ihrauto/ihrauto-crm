<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckinResource;
use App\Models\Checkin;
use App\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    /**
     * Defense-in-depth guard for API actions. The route-model-binding already
     * invokes TenantScope, so cross-tenant resources should 404 there — but
     * we re-check explicitly so a bug in the scope (or a future refactor that
     * bypasses it) cannot leak data across tenants.
     */
    private function assertApiTenantContext(Request $request, ?\Illuminate\Database\Eloquent\Model $resource = null): void
    {
        abort_unless(
            $request->attributes->get('tenant_api_token_id'),
            403,
            'Valid API token required.'
        );

        if ($resource !== null && $resource->tenant_id !== tenant_id()) {
            // 404 (not 403) to avoid leaking existence of a resource in
            // another tenant.
            abort(404);
        }
    }

    /**
     * Get customer history
     */
    public function getCustomerHistory(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $checkins = $customer->checkins()
                ->with(['vehicle'])
                ->latest('checkin_time')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'email' => $customer->email,
                    ],
                    'checkins' => CheckinResource::collection($checkins),
                ],
                'meta' => [
                    'total' => $checkins->count(),
                    'customer_id' => $customer->id,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get customer details with vehicles
     */
    public function getCustomerDetails(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $customer->load(['vehicles' => function ($query) {
                $query->where('is_active', true);
            }]);

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'email' => $customer->email,
                        'address' => $customer->address,
                    ],
                    'vehicles' => $customer->vehicles->map(function ($vehicle) {
                        return [
                            'id' => $vehicle->id,
                            'license_plate' => $vehicle->license_plate,
                            'display_name' => $vehicle->display_name,
                            'make' => $vehicle->make,
                            'model' => $vehicle->model,
                            'year' => $vehicle->year,
                            'color' => $vehicle->color,
                        ];
                    }),
                ],
                'meta' => [
                    'customer_id' => $customer->id,
                    'vehicles_count' => $customer->vehicles->count(),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get active checkins
     */
    public function getActiveCheckins(Request $request): JsonResponse
    {
        $this->assertApiTenantContext($request);

        try {
            $checkins = Checkin::with(['customer', 'vehicle'])
                ->active()
                ->latest('checkin_time')
                ->get();

            return response()->json([
                'success' => true,
                'data' => CheckinResource::collection($checkins),
                'meta' => [
                    'total' => $checkins->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active checkins',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get checkins statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $this->assertApiTenantContext($request);

        try {
            $stats = [
                'today_checkins' => Checkin::today()->count(),
                'in_progress' => Checkin::inProgress()->count(),
                'completed_today' => Checkin::completed()->whereDate('checkout_time', today())->count(),
                'pending' => Checkin::pending()->count(),
                'total_active' => Checkin::active()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'meta' => [
                    'generated_at' => now()->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
