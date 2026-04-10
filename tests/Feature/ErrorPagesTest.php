<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint B.13 — custom branded error pages.
 *
 * Verifies that each error view renders correctly, contains the expected error
 * code, branding, and doesn't leak debug information.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Force debug off for tests (Laravel otherwise shows the Ignition page)
        config(['app.debug' => false]);
    }

    #[Test]
    public function not_found_route_renders_custom_404_page(): void
    {
        $response = $this->get('/this-route-does-not-exist-anywhere');

        $response->assertNotFound();
        $response->assertSee('Error 404');
        $response->assertSee('Page Not Found');
        // Must render the branded layout
        $response->assertSee(config('app.name'));
    }

    #[Test]
    public function custom_403_view_renders(): void
    {
        // Render the 403 view directly to verify it compiles without errors.
        // (Reproducing a 403 HTTP response reliably is flaky across middleware
        //  layers, so we assert on the view template directly.)
        $view = view('errors.403', [
            'exception' => new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Test'),
        ])->render();

        $this->assertStringContainsString('Error 403', $view);
        $this->assertStringContainsString('Access Denied', $view);
        $this->assertStringContainsString(config('app.name'), $view);
    }

    #[Test]
    public function custom_500_view_renders(): void
    {
        $view = view('errors.500', [
            'exception' => new \Exception('Test'),
        ])->render();

        $this->assertStringContainsString('Error 500', $view);
        $this->assertStringContainsString('Something Went Wrong', $view);
        $this->assertStringContainsString(config('app.name'), $view);
    }

    #[Test]
    public function custom_503_view_renders(): void
    {
        $view = view('errors.503', [
            'exception' => new \Exception('Test'),
        ])->render();

        $this->assertStringContainsString('Error 503', $view);
        $this->assertStringContainsString('Service Temporarily Unavailable', $view);
        $this->assertStringContainsString(config('app.name'), $view);
    }

    #[Test]
    public function error_pages_do_not_leak_stack_traces(): void
    {
        $response = $this->get('/this-route-does-not-exist-anywhere');

        // No stack trace keywords should appear
        $response->assertDontSee('Stack trace');
        $response->assertDontSee('.php:');
        $response->assertDontSee('vendor/');
    }

    #[Test]
    public function error_pages_show_dashboard_link_for_authenticated_users(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/this-route-does-not-exist-anywhere');

        $response->assertNotFound();
        $response->assertSee('Back to Dashboard');
    }

    #[Test]
    public function error_pages_show_login_link_for_guests(): void
    {
        $response = $this->get('/this-route-does-not-exist-anywhere');

        $response->assertNotFound();
        $response->assertSee('Sign In');
    }
}
