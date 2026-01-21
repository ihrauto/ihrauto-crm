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
        // Accept both 'query' and 'q' for compatibility
        $searchQuery = $request->get('query') ?? $request->get('q');
        $limit = $request->get('limit', 10);

        // Validate - minimum 2 characters
        if (!$searchQuery || strlen($searchQuery) < 2) {
            return response()->json([]);
        }

        try {
            // Normalize for plate search
            $normalizedPlate = strtoupper(str_replace(' ', '', trim($searchQuery)));
            $searchTerm = strtolower(trim($searchQuery));

            $customers = Customer::with([
                'vehicles' => function ($queryBuilder) use ($normalizedPlate) {
                    $queryBuilder->whereRaw("UPPER(REPLACE(license_plate, ' ', '')) LIKE ?", ["%{$normalizedPlate}%"]);
                }
            ])
                ->where(function ($queryBuilder) use ($normalizedPlate, $searchTerm) {
                    // Search by customer name (case-insensitive)
                    $queryBuilder->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                        // Or by phone
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$searchTerm}%"])
                        // Or by email
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                        // Or by license plate
                        ->orWhereHas('vehicles', function ($q) use ($normalizedPlate) {
                        $q->whereRaw("UPPER(REPLACE(license_plate, ' ', '')) LIKE ?", ["%{$normalizedPlate}%"]);
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
