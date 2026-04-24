<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags every response on a legacy (non-/api/v1) path with RFC 8594
 * deprecation signalling AND writes a structured log line so ops can
 * prove whether anyone still hits these endpoints before the sunset
 * date removes them. See routes/api.php for the removal checklist.
 */
class AddLegacyApiDeprecationHeaders
{
    /**
     * Sunset date is mirrored in routes/api.php. Keep both in sync.
     */
    private const SUNSET = 'Tue, 30 Jun 2026 23:59:59 GMT';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', self::SUNSET);
        $response->headers->set('Link', '</api/v1>; rel="successor-version"');

        // B3 (sprint 2026-04-24): record legacy usage so we can confirm
        // the sunset date is safe. The tenant API token id (not the
        // secret) lets ops contact the owning tenant before removal.
        Log::channel(config('logging.default'))->info('legacy_api_call', [
            'path' => $request->path(),
            'method' => $request->method(),
            'tenant_api_token_id' => $request->attributes->get('tenant_api_token_id'),
            'tenant_api_token_prefix' => $request->attributes->get('tenant_api_token_prefix'),
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
