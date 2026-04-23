<?php

namespace App\Models;

use App\Support\TenantCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'token_prefix',
        'token_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function issue(Tenant $tenant, string $name = 'default'): array
    {
        $plainTextToken = 'tk_'.Str::random(48);

        $token = static::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'token_prefix' => substr($plainTextToken, 0, 12),
            'token_hash' => hash('sha256', $plainTextToken),
        ]);

        TenantCache::forgetToken($token);

        return [$token, $plainTextToken];
    }

    /**
     * Look up an active, non-revoked token by its plain-text value.
     *
     * SECURITY INVARIANTS — do not change without extreme care:
     *
     *  1. `TenantApiToken` intentionally does NOT use the BelongsToTenant trait.
     *     This lookup happens BEFORE the tenant context is resolved (it's what
     *     resolves the tenant), so a global scope would either be a no-op or,
     *     worse, filter against a stale/previous tenant.
     *
     *  2. `->with('tenant')` is load-bearing. Callers (notably
     *     AuthenticateTenantApiToken middleware) use `$apiToken->tenant` to
     *     set the tenant context — dropping the eager-load would trigger a
     *     lazy query that runs under some OTHER tenant's scope.
     *
     *  3. `whereNull('revoked_at')` must stay. Revocation is the ONLY way to
     *     invalidate a leaked token; removing this filter hands attackers a
     *     permanent bypass.
     *
     *  4. The hash compare happens inside the DB on a fixed-length sha256
     *     digest. Since the attacker cannot compute the digest without the
     *     plaintext, byte-prefix timing on the WHERE clause does not leak
     *     useful information. Token entropy (`Str::random(48)` ≈ 248 bits)
     *     makes brute force infeasible.
     *
     * See tests/Feature/TenantApiTokenInvariantTest.php for regression checks.
     */
    public static function findActiveByPlainTextToken(?string $plainTextToken): ?self
    {
        if (! $plainTextToken) {
            return null;
        }

        return static::with('tenant')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->whereNull('revoked_at')
            ->first();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->saveQuietly();
        TenantCache::forgetToken($this);
    }
}
