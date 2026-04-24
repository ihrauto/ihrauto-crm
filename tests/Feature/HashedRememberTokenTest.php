<?php

namespace Tests\Feature;

use App\Auth\HashedEloquentUserProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * C2 (sprint 2026-04-24): remember_token must be stored as a SHA-256
 * digest, never plaintext. These tests lock the invariant so the
 * provider wiring cannot silently regress.
 */
class HashedRememberTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function auth_provider_is_the_hashed_subclass(): void
    {
        $provider = Auth::guard('web')->getProvider();

        $this->assertInstanceOf(
            HashedEloquentUserProvider::class,
            $provider,
            'config/auth.php should point the users provider at driver hashed-eloquent.'
        );
    }

    #[Test]
    public function update_remember_token_stores_the_sha256_digest(): void
    {
        $user = User::factory()->create();
        $provider = Auth::guard('web')->getProvider();

        $plainToken = Str::random(60);
        $provider->updateRememberToken($user, $plainToken);

        $user->refresh();
        $this->assertNotSame($plainToken, $user->getRememberToken(),
            'Plaintext must never land in the remember_token column.');
        $this->assertSame(hash('sha256', $plainToken), $user->getRememberToken());
    }

    #[Test]
    public function retrieve_by_token_accepts_the_plaintext_and_finds_the_user(): void
    {
        $user = User::factory()->create();
        $provider = Auth::guard('web')->getProvider();

        $plainToken = Str::random(60);
        $provider->updateRememberToken($user, $plainToken);

        $resolved = $provider->retrieveByToken($user->getKey(), $plainToken);

        $this->assertNotNull($resolved, 'Valid plaintext token must resolve to the user.');
        $this->assertTrue($resolved->is($user));
    }

    #[Test]
    public function retrieve_by_token_rejects_wrong_token(): void
    {
        $user = User::factory()->create();
        $provider = Auth::guard('web')->getProvider();

        $provider->updateRememberToken($user, Str::random(60));

        $this->assertNull($provider->retrieveByToken($user->getKey(), 'guessed-wrong'));
    }

    #[Test]
    public function retrieve_by_token_rejects_legacy_plaintext_equal_to_stored_value(): void
    {
        // If someone tries to pass the already-hashed stored value as the
        // "plaintext" cookie, the provider must still hash it before
        // comparing. This specifically guards against a regression where
        // the hash step is accidentally removed on the read path.
        $user = User::factory()->create();
        $provider = Auth::guard('web')->getProvider();

        $plainToken = Str::random(60);
        $provider->updateRememberToken($user, $plainToken);

        $storedHash = $user->fresh()->getRememberToken();

        // Passing the stored hash itself must NOT authenticate — only
        // the plaintext that hashes to it does.
        $this->assertNull($provider->retrieveByToken($user->getKey(), $storedHash));
    }

    #[Test]
    public function empty_or_null_token_never_returns_a_user(): void
    {
        $user = User::factory()->create();
        $provider = Auth::guard('web')->getProvider();

        $provider->updateRememberToken($user, Str::random(60));

        $this->assertNull($provider->retrieveByToken($user->getKey(), ''));
        $this->assertNull($provider->retrieveByToken($user->getKey(), null));
    }

    #[Test]
    public function user_without_stored_token_is_not_returned(): void
    {
        $user = User::factory()->create(['remember_token' => null]);
        $provider = Auth::guard('web')->getProvider();

        $this->assertNull($provider->retrieveByToken($user->getKey(), 'any-plaintext'));
    }
}
