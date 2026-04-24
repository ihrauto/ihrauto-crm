<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

/**
 * Security review M-5 / C2: a drop-in replacement for Laravel's
 * `EloquentUserProvider` that stores `remember_token` as a SHA-256 hash
 * instead of plaintext.
 *
 * Why:
 *   The default provider stores the 60-char random token as-is in
 *   `users.remember_token`. A DB dump or accidental log of that column
 *   is immediately exploitable — set a browser cookie to the leaked
 *   value and you're logged in as that user indefinitely.
 *
 * How:
 *   On write (updateRememberToken) we hash the plaintext with SHA-256
 *   before persisting.
 *   On read (retrieveByToken) we hash the incoming cookie token, then
 *   `hash_equals()` against the stored digest.
 *
 * Why SHA-256 and not bcrypt:
 *   remember_token is already high-entropy (60-char Str::random, ~355
 *   bits). Brute force is infeasible, so a slow password hash buys
 *   nothing and hurts login latency. SHA-256 prevents trivial rainbow /
 *   precomputation attacks at zero runtime cost.
 *
 * Rollout:
 *   - A migration (…_null_legacy_remember_tokens) nulls out existing
 *     plaintext tokens on deploy — every remember-me cookie issued
 *     before this change stops working, users simply re-log. Cookie is
 *     still valid for the active SESSION_LIFETIME.
 *   - No dual-verify window: we intentionally fail closed on old
 *     tokens rather than keep a legacy compare path around.
 */
class HashedEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Hash token values stored in the DB using this algorithm. 'sha256'
     * maps to the same length the existing column reserves (64 hex chars;
     * the column is 100 chars).
     */
    public const HASH_ALGO = 'sha256';

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        if ($token === null || $token === '') {
            return null;
        }

        $model = $this->createModel();

        $retrievedModel = $this->newModelQuery($model)->where(
            $model->getAuthIdentifierName(), $identifier
        )->first();

        if (! $retrievedModel) {
            return null;
        }

        $storedHash = $retrievedModel->getRememberToken();
        if (! $storedHash) {
            return null;
        }

        $incomingHash = self::hashToken($token);

        return hash_equals($storedHash, $incomingHash) ? $retrievedModel : null;
    }

    public function updateRememberToken(UserContract $user, #[\SensitiveParameter] $token)
    {
        $user->setRememberToken(self::hashToken($token));

        $timestamps = $user->timestamps;
        $user->timestamps = false;
        $user->save();
        $user->timestamps = $timestamps;
    }

    /**
     * Produce the stored-form digest for a plaintext remember-me token.
     * Exposed so ops / tests can reproduce the transform without having
     * to re-instantiate the provider.
     */
    public static function hashToken(string $plaintext): string
    {
        return hash(self::HASH_ALGO, $plaintext);
    }
}
