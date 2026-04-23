<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint A.3 — Google SSO wrong-tenant/soft-deleted login.
 *
 * Verifies:
 * - Soft-deleted users cannot log in via Google
 * - Inactive users (is_active=false) are rejected
 * - Users whose tenant is suspended/archived are rejected
 * - Valid users can still log in
 * - New users (not in DB) are redirected to company creation
 */
class SocialAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function mockGoogleUser(string $email, string $name = 'Test User'): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getId')->andReturn('google-id-123');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function active_user_can_log_in_via_google(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'alice@example.com',
            'is_active' => true,
        ]);

        $this->mockGoogleUser('alice@example.com');

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function inactive_user_cannot_log_in_via_google(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'bob@example.com',
            'is_active' => false, // suspended / invite not accepted
        ]);

        $this->mockGoogleUser('bob@example.com');

        $response = $this->get('/auth/google/callback');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function soft_deleted_user_cannot_log_in_via_google(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'carol@example.com',
            'is_active' => true,
        ]);
        $user->delete(); // soft-delete

        $this->mockGoogleUser('carol@example.com');

        $response = $this->get('/auth/google/callback');

        $this->assertGuest();
        // Soft-deleted user is treated as a new user — redirected to create company
        // because the lookup now correctly excludes deleted users.
        $response->assertRedirect(route('auth.create-company'));
    }

    #[Test]
    public function user_whose_tenant_is_suspended_cannot_log_in(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => false]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'dave@example.com',
            'is_active' => true,
        ]);

        $this->mockGoogleUser('dave@example.com');

        $response = $this->get('/auth/google/callback');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function new_google_user_is_redirected_to_company_creation(): void
    {
        $this->mockGoogleUser('newuser@example.com', 'New User');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('auth.create-company'));
        $this->assertEquals('newuser@example.com', session('google_user.email'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
