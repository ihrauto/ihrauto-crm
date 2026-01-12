<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint for monitoring.
     */
    public function check()
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = ['status' => 'ok'];
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['checks']['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
        }

        // Cache check (optional)
        try {
            cache()->put('health_check', true, 1);
            cache()->forget('health_check');
            $health['checks']['cache'] = ['status' => 'ok'];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'warning',
                'message' => 'Cache not available',
            ];
        }

        $httpStatus = $health['status'] === 'ok' ? 200 : 503;

        return response()->json($health, $httpStatus);
    }
}
