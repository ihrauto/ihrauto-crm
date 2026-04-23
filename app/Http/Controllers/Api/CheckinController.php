<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckinResource;
use App\Models\Checkin;
use App\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    use ApiResponse;

    /**
     * Defense-in-depth guard for API actions. The route-model-binding
     * already invokes TenantScope, so cross-tenant resources should 404
     * there — we re-check explicitly so a bug in the scope (or a future
     * refactor that bypasses it) cannot leak data across tenants.
     */
    private function assertApiTenantContext(Request $request, ?\Illuminate\Database\Eloquent\Model $resource = null): void
    {
        abort_unless(
            $request->attributes->get('tenant_api_token_id'),
            403,
            'Valid API token required.'
        );

        if ($resource !== null && $resource->tenant_id !== tenant_id()) {
            // 404 (not 403) to avoid leaking the existence of a resource
            // in another tenant.
            abort(404);
        }
    }

    public function getCustomerHistory(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $checkins = $customer->checkins()
                ->with(['vehicle'])
                ->latest('checkin_time')
                ->limit(50)
                ->get();

            return $this->apiOk([
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'checkins' => CheckinResource::collection($checkins),
            ], [
                'total' => $checkins->count(),
                'customer_id' => $customer->id,
            ]);
        } catch (ModelNotFoundException) {
            return $this->apiError('Customer not found', 404, 'not_found');
        } catch (\Throwable $e) {
            report($e);

            return $this->apiError(
                'Failed to fetch customer history',
                500,
                'internal_error',
                ['exception' => $e->getMessage()],
            );
        }
    }

    public function getCustomerDetails(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $customer->load(['vehicles' => fn ($q) => $q->where('is_active', true)]);

            return $this->apiOk([
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                ],
                'vehicles' => $customer->vehicles->map(fn ($vehicle) => [
                    'id' => $vehicle->id,
                    'license_plate' => $vehicle->license_plate,
                    'display_name' => $vehicle->display_name,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'color' => $vehicle->color,
                ]),
            ], [
                'customer_id' => $customer->id,
                'vehicles_count' => $customer->vehicles->count(),
            ]);
        } catch (ModelNotFoundException) {
            return $this->apiError('Customer not found', 404, 'not_found');
        } catch (\Throwable $e) {
            report($e);

            return $this->apiError(
                'Failed to fetch customer details',
                500,
                'internal_error',
                ['exception' => $e->getMessage()],
            );
        }
    }

    public function getActiveCheckins(Request $request): JsonResponse
    {
        $this->assertApiTenantContext($request);

        try {
            $checkins = Checkin::with(['customer', 'vehicle'])
                ->active()
                ->latest('checkin_time')
                ->get();

            return $this->apiOk(
                CheckinResource::collection($checkins),
                ['total' => $checkins->count()]
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->apiError(
                'Failed to fetch active checkins',
                500,
                'internal_error',
                ['exception' => $e->getMessage()],
            );
        }
    }

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

            return $this->apiOk($stats, ['generated_at' => now()->format('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            report($e);

            return $this->apiError(
                'Failed to fetch statistics',
                500,
                'internal_error',
                ['exception' => $e->getMessage()],
            );
        }
    }
}
