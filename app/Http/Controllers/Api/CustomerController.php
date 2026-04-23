<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
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
     * Search customers by query
     */
    public function search(Request $request): JsonResponse
    {
        $this->assertApiTenantContext($request);

        // Accept both 'query' and 'q' for compatibility
        $searchQuery = $request->get('query') ?? $request->get('q');
        $limit = $request->get('limit', 10);

        // Validate - minimum 2 characters
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

            // Return simple array for compatibility with checkin.blade.php JavaScript
            return response()->json($customers);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Get customer details
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $customer->load(['vehicles', 'checkins.vehicle']);

            return response()->json([
                'success' => true,
                'data' => new CustomerResource($customer),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving customer',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get vehicles for a specific customer
     */
    public function vehicles(Request $request, Customer $customer): JsonResponse
    {
        $this->assertApiTenantContext($request, $customer);

        try {
            $vehicles = $customer->vehicles()
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\VehicleResource::collection($vehicles),
                'meta' => [
                    'customer_id' => $customer->id,
                    'total' => $vehicles->count(),
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
                'message' => 'Error retrieving customer vehicles',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
