<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint A.2 — Invite token security hardening.
 *
 * Verifies:
 * - Timing-safe comparison (hash_equals) is used to find users by invite token
 * - Short or empty tokens are rejected without a DB hit
 * - Expired tokens are rejected
 * - Cleared tokens (nullified after setup) cannot be reused
 * - A valid token matching user A cannot be used to hijack user B's account
 */
class InviteTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->tenant = Tenant::factory()->create();
    }

    #[Test]
    public function valid_invite_token_shows_setup_form(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invite_token' => $token,
            'invite_expires_at' => now()->addDay(),
            'is_active' => false,
        ]);

        $response = $this->get(route('invite.setup', ['token' => $token]));

        $response->assertOk();
    }

    #[Test]
    public function invalid_invite_token_is_rejected(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invite_token' => bin2hex(random_bytes(32)),
            'invite_expires_at' => now()->addDay(),
        ]);

        $response = $this->get(route('invite.setup', ['token' => bin2hex(random_bytes(32))]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function expired_invite_token_is_rejected(): void
    {
        $token = bin2hex(random_bytes(32));
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invite_token' => $token,
            'invite_expires_at' => now()->subDay(), // expired yesterday
        ]);

        $response = $this->get(route('invite.setup', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function empty_string_token_is_rejected(): void
    {
        // The route pattern requires a token parameter — passing an empty string
        // means the URL doesn't match the invite route at all.
        $response = $this->get('/invite/');
        $response->assertStatus(404);
    }

    #[Test]
    public function short_token_is_rejected(): void
    {
        $response = $this->get(route('invite.setup', ['token' => 'short']));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function cleared_invite_token_cannot_be_reused(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invite_token' => $token,
            'invite_expires_at' => now()->addDay(),
            'is_active' => false,
        ]);
        $user->assignRole('technician');

        // Complete setup successfully
        $this->post(route('invite.setup.store', ['token' => $token]), [
            // L-1: hardened password rule.
            'password' => 'CompliantPass12',
            'password_confirmation' => 'CompliantPass12',
        ])->assertRedirect(route('dashboard'));

        // Log out to try the token again
        auth()->logout();

        // Attempt to reuse the same token
        $response = $this->get(route('invite.setup', ['token' => $token]));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function token_for_user_a_cannot_access_user_b(): void
    {
        $tokenA = bin2hex(random_bytes(32));
        $tokenB = bin2hex(random_bytes(32));

        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'a@example.com',
            'invite_token' => $tokenA,
            'invite_expires_at' => now()->addDay(),
        ]);

        $userB = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'b@example.com',
            'invite_token' => $tokenB,
            'invite_expires_at' => now()->addDay(),
        ]);

        // Request setup for user A's token
        $response = $this->get(route('invite.setup', ['token' => $tokenA]));
        $response->assertOk();
        $response->assertSee('a@example.com');
        $response->assertDontSee('b@example.com');
    }

    #[Test]
    public function invite_token_lookup_uses_timing_safe_comparison(): void
    {
        // This is a smoke test: the fix uses hash_equals().
        // We verify the behavior: an attacker guessing bytes can't distinguish
        // "close" tokens from "far" tokens via timing. Full timing-attack tests
        // require statistical analysis, but we can at least verify correct tokens
        // match and incorrect ones don't.
        $correctToken = str_repeat('a', 64);
        $almostCorrect = str_repeat('a', 63).'b'; // differs only in last byte
        $wayOff = str_repeat('z', 64);

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invite_token' => $correctToken,
            'invite_expires_at' => now()->addDay(),
        ]);

        $this->get(route('invite.setup', ['token' => $correctToken]))->assertOk();
        $this->get(route('invite.setup', ['token' => $almostCorrect]))->assertRedirect(route('login'));
        $this->get(route('invite.setup', ['token' => $wayOff]))->assertRedirect(route('login'));
    }
}
