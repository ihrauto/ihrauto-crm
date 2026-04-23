<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantApiToken;
use Illuminate\Support\Facades\Cache;

class TenantCache
{
    public static function tenantIdKey(int|string $tenantId): string
    {
        return "tenant.id.{$tenantId}";
    }

    public static function tenantSubdomainKey(string $subdomain): string
    {
        return "tenant.subdomain.{$subdomain}";
    }

    public static function tenantDomainKey(string $domain): string
    {
        return "tenant.domain.{$domain}";
    }

    public static function apiTokenKey(string $plainTextToken): string
    {
        return self::apiTokenKeyByHash(hash('sha256', $plainTextToken));
    }

    public static function apiTokenKeyByHash(string $tokenHash): string
    {
        return "tenant_api_token.{$tokenHash}";
    }

    public static function apiTokenLastUsedKey(int|string $tokenId): string
    {
        return "tenant_api_token.last_used.{$tokenId}";
    }

    public static function forgetTenant(Tenant $tenant): void
    {
        Cache::forget(self::tenantIdKey($tenant->id));

        if (! empty($tenant->subdomain)) {
            Cache::forget(self::tenantSubdomainKey($tenant->subdomain));
        }

        if (! empty($tenant->domain)) {
            Cache::forget(self::tenantDomainKey($tenant->domain));
        }

        TenantApiToken::query()
            ->where('tenant_id', $tenant->id)
            ->pluck('token_hash')
            ->filter()
            ->each(fn (string $tokenHash) => Cache::forget(self::apiTokenKeyByHash($tokenHash)));
    }

    public static function forgetToken(TenantApiToken $token): void
    {
        Cache::forget(self::apiTokenKeyByHash($token->token_hash));
        Cache::forget(self::apiTokenLastUsedKey($token->id));
    }
}
