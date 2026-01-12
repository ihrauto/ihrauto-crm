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
     * Search customers by query
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 10);

        try {
            // Search by license plate (normalized search)
            $normalizedSearch = strtoupper(str_replace(' ', '', trim($query)));

            $customers = Customer::with(['vehicles' => function ($queryBuilder) use ($normalizedSearch) {
                $queryBuilder->whereRaw('UPPER(REPLACE(license_plate, " ", "")) LIKE ?', ["%{$normalizedSearch}%"]);
            }])
                ->where(function ($queryBuilder) use ($normalizedSearch, $query) {
                    $queryBuilder->whereHas('vehicles', function ($q) use ($normalizedSearch) {
                        $q->whereRaw('UPPER(REPLACE(license_plate, " ", "")) LIKE ?', ["%{$normalizedSearch}%"]);
                    })
                        ->orWhere('name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => CustomerResource::collection($customers),
                'meta' => [
                    'query' => $query,
                    'total' => $customers->count(),
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching customers',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get customer details
     */
    public function show(Customer $customer): JsonResponse
    {
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
    public function vehicles(Customer $customer): JsonResponse
    {
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
