<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddLegacyApiDeprecationHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', 'Tue, 30 Jun 2026 23:59:59 GMT');
        $response->headers->set('Link', '</api/v1>; rel="successor-version"');

        return $response;
    }
}
