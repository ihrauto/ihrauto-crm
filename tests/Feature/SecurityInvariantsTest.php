<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Invariants pinned by the 2026-04-24 security review. Each test
 * corresponds to a finding and guards against silent regression. If one
 * of these fails, pause before merging — something that used to be safe
 * is no longer safe.
 */
class SecurityInvariantsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function h1_two_factor_required_is_not_mass_assignable_on_tenant(): void
    {
        // Laravel silently drops unknown fillable fields by default (rather
        // than raising MassAssignmentException), so we assert behaviour
        // directly: the fill() call must not set the attribute, and a save
        // must not persist it.
        $tenant = Tenant::factory()->create();

        $tenant->fill(['two_factor_required' => true, 'name' => 'Renamed Co']);
        $tenant->save();

        $tenant->refresh();
        $this->assertSame('Renamed Co', $tenant->name, 'safe fields still flow through fill');
        $this->assertFalse((bool) $tenant->getRawOriginal('two_factor_required'),
            'two_factor_required must never be set via mass assignment');
    }

    #[Test]
    public function m6_invite_token_is_hidden_on_user_serialization(): void
    {
        $user = User::factory()->create([
            'invite_token' => str_repeat('a', 64),
            'invite_expires_at' => now()->addDay(),
        ]);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('invite_token', $serialized);
        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
    }

    #[Test]
    public function m1_html_response_carries_csp_and_cross_origin_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        // The big-win directives must stay present even if we relax the rest.
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    #[Test]
    public function l1_password_policy_requires_at_least_twelve_mixed_characters(): void
    {
        // Policy is enforced via Password::defaults() in AppServiceProvider::boot.
        // Registration is the cheapest path that exercises the rule end-to-end.
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');

        $response = $this
            ->from('/register')
            ->post('/register', [
                'name' => 'Weak User',
                'email' => 'weak@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'company_name' => 'Weak Co',
            ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    #[Test]
    public function robots_txt_disallows_authenticated_paths(): void
    {
        // robots.txt is served by the web server (Apache/nginx), not by
        // Laravel's router — an HTTP test would hit the route layer and
        // return 404. Read the file straight from public/.
        $body = (string) file_get_contents(public_path('robots.txt'));

        $this->assertNotSame('', $body, 'robots.txt must exist');

        foreach (['/admin', '/api', '/login', '/register', '/dashboard', '/management', '/dev/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $body,
                "robots.txt must disallow {$path}");
        }
    }
}
