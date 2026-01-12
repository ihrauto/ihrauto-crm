<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckinResource;
use App\Models\Checkin;
use App\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class CheckinController extends Controller
{
    /**
     * Get customer history
     */
    public function getCustomerHistory(Customer $customer): JsonResponse
    {
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
    public function getCustomerDetails(Customer $customer): JsonResponse
    {
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
    public function getActiveCheckins(): JsonResponse
    {
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
    public function getStatistics(): JsonResponse
    {
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
