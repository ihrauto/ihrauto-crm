<?php

namespace App\Http\Middleware;

use App\Models\TenantApiToken;
use App\Support\TenantCache;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantApiToken
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->clear();

        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return $this->unauthorized('Missing API token.');
        }

        $cacheKey = TenantCache::apiTokenKey($plainTextToken);

        $apiToken = Cache::remember($cacheKey, 60, fn () => TenantApiToken::findActiveByPlainTextToken($plainTextToken));

        if (! $apiToken || ! $apiToken->tenant) {
            return $this->unauthorized('Invalid API token.');
        }

        $tenant = $apiToken->tenant;

        if (! $tenant->is_active) {
            return response()->json([
                'error' => 'Tenant inactive',
                'message' => 'This tenant account is currently inactive.',
            ], 403);
        }

        if ($tenant->is_expired) {
            return response()->json([
                'error' => 'Tenant expired',
                'message' => 'This tenant subscription has expired.',
            ], 403);
        }

        $this->tenantContext->set($tenant, $request, $apiToken);

        $request->attributes->set('tenant_api_token_id', $apiToken->id);
        $request->attributes->set('tenant_api_token_prefix', $apiToken->token_prefix);

        $this->touchToken($apiToken);

        return $next($request);
    }

    private function touchToken(TenantApiToken $apiToken): void
    {
        $cacheKey = TenantCache::apiTokenLastUsedKey($apiToken->id);

        if (Cache::add($cacheKey, true, now()->addMinutes(5))) {
            $apiToken->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
        ], 401);
    }
}
