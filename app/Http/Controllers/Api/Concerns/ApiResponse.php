<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * C-09: unified JSON response shape for the tenant API.
 *
 * Every API controller uses these helpers instead of hand-rolling the
 * envelope. Downstream consumers can rely on a single shape:
 *
 *   - Success: { "success": true, "data": ..., "meta": {...}? }
 *   - Error:   { "success": false, "message": "...", "error": "...", "details": {...}? }
 *
 * Existing callers that checked `success: true/false` continue to work.
 */
trait ApiResponse
{
    protected function apiOk(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function apiError(
        string $message,
        int $status = 500,
        ?string $code = null,
        array $details = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($code) {
            $payload['error'] = $code;
        }

        if (! empty($details)) {
            $payload['details'] = $details;
        }

        // Debug detail only leaks in local/testing; production stays clean.
        if (app()->hasDebugModeEnabled() && isset($details['exception']) && is_string($details['exception'])) {
            $payload['debug'] = $details['exception'];
        }

        return response()->json($payload, $status);
    }
}
