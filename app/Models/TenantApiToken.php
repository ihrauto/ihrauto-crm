<?php

namespace App\Models;

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
        $plainTextToken = 'tk_' . Str::random(48);

        $token = static::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'token_prefix' => substr($plainTextToken, 0, 12),
            'token_hash' => hash('sha256', $plainTextToken),
        ]);

        return [$token, $plainTextToken];
    }

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
    }
}
