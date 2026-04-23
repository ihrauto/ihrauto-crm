<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    /**
     * Defense-in-depth guard — see Api\CheckinController for rationale.
     */
    private function assertApiTenantContext(Request $request, ?\Illuminate\Database\Eloquent\Model $resource = null): void
    {
        abort_unless(
            $request->attributes->get('tenant_api_token_id'),
            403,
            'Valid API token required.'
        );

        if ($resource !== null && $resource->tenant_id !== tenant_id()) {
            abort(404);
        }
    }

    /**
     * Legacy-shape search endpoint used by the checkin Blade. Returns a
     * bare array (no envelope) for backwards compatibility; do not migrate
     * to apiOk() without coordinating with the Blade JS consumers.
     */
    public function search(Request $request): JsonResponse
    {
        $this->assertApiTenantContext($request);

        $searchQuery = $request->get('query') ?? $request->get('q');
        $limit = $request->get('limit', 10);

        if (! $searchQuery || strlen($searchQuery) < 2) {
            return response()->json([]);
        }

        try {
            $searchTerm = strtolower(trim($searchQuery));
            [$plateExpr, $plateBindings] = \App\Support\LicensePlate::whereExpression($searchQuery, like: true);

            $customers = Customer::with([
                'vehicles' => function ($queryBuilder) use ($plateExpr, $plateBindings) {
                    $queryBuilder->whereRaw($plateExpr, $plateBindings);
                },
            ])
                ->where(function ($queryBuilder) use ($plateExpr, $plateBindings, $searchTerm) {
                    $queryBuilder->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$searchTerm}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                        ->orWhereHas('vehicles', function ($q) use ($plateExpr, $plateBindings) {
                            $q->whereRaw($plateExpr, $plateBindings);
                        });
                })
                ->limit($limit)
                ->get(['id', 'name', 'phone', 'email']);

            return response()->json($customers);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([]);
        }
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $customer->load(['vehicles', 'checkins.vehicle']);

            return $this->apiOk(new CustomerResource($customer));
        } catch (ModelNotFoundException) {
            return $this->apiError('Customer not found', 404, 'not_found');
        } catch (\Throwable $e) {
            report($e);

            return $this->apiError(
                'Error retrieving customer',
                500,
                'internal_error',
                ['exception' => $e->getMessage()],
            );
        }
    }

    public function vehicles(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $vehicles = $customer->vehicles()->where('is_active', true)->get();

            return $this->apiOk(
                \App\Http\Resources\VehicleResource::collection($vehicles),
                [
                    'customer_id' => $customer->id,
                    'total' => $vehicles->count(),
                ]
            );
        } catch (ModelNotFoundException) {
            return $this->apiError('Customer not found', 404, 'not_found');
        } catch (\Throwable $e) {
            report($e);

            return $this->apiError(
                'Error retrieving customer vehicles',
                500,
                'internal_error',
                ['exception' => $e->getMessage()],
            );
        }
    }
}
