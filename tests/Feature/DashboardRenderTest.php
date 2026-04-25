<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\DashboardWidgetCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function dashboard_renders_role_defaults_when_no_preference_is_stored()
    {
        $this->assertNull($this->admin->dashboard_widgets);

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk();
        // Active Jobs is in admin's role defaults.
        $response->assertSee('Active Jobs');
    }

    #[Test]
    public function dashboard_renders_only_the_enabled_widgets()
    {
        $this->admin->forceFill([
            'dashboard_widgets' => [
                'version' => DashboardWidgetCatalog::VERSION,
                'enabled' => ['pending_jobs'],
            ],
        ])->save();

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk();
        // Use markup that only appears in the rendered partial — generic
        // labels like "Active Jobs" are also listed in the Studio panel
        // (which lists EVERY available widget, including the disabled
        // ones), so they would yield false positives.
        $response->assertSee('Start a job'); // pending_jobs partial CTA
        $response->assertDontSee('View all jobs'); // active_jobs partial CTA
        $response->assertDontSee('Schedule one now'); // todays_schedule empty-state
    }

    #[Test]
    public function dashboard_shows_empty_state_when_user_disables_everything()
    {
        $this->admin->forceFill([
            'dashboard_widgets' => [
                'version' => DashboardWidgetCatalog::VERSION,
                'enabled' => [],
            ],
        ])->save();

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Your dashboard is empty');
    }

    #[Test]
    public function studio_trigger_appears_in_header_on_dashboard_route()
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Customize dashboard widgets');
    }

    #[Test]
    public function studio_trigger_does_not_appear_outside_dashboard()
    {
        $response = $this->actingAs($this->admin)->get('/customers');

        $response->assertOk();
        $response->assertDontSee('Customize dashboard widgets');
    }

    #[Test]
    public function widgets_fragment_returns_only_the_grid_html()
    {
        $this->admin->forceFill([
            'dashboard_widgets' => [
                'version' => DashboardWidgetCatalog::VERSION,
                'enabled' => ['active_jobs'],
            ],
        ])->save();

        $response = $this->actingAs($this->admin)->get('/dashboard/widgets-fragment');

        $response->assertOk();
        // Includes the rendered widget partial...
        $response->assertSee('View all jobs');
        // ...but NOT the page chrome (welcome section, layout shell).
        $response->assertDontSee('Welcome back');
        $response->assertDontSee('<html');
    }

    #[Test]
    public function widgets_fragment_requires_authentication()
    {
        $this->get('/dashboard/widgets-fragment')->assertRedirect('/login');
    }

    #[Test]
    public function widgets_fragment_renders_empty_state_when_nothing_enabled()
    {
        $this->admin->forceFill([
            'dashboard_widgets' => [
                'version' => DashboardWidgetCatalog::VERSION,
                'enabled' => [],
            ],
        ])->save();

        $response = $this->actingAs($this->admin)->get('/dashboard/widgets-fragment');

        $response->assertOk();
        $response->assertSee('Your dashboard is empty');
    }

    #[Test]
    public function dashboard_renders_with_every_widget_enabled_for_an_admin()
    {
        // Audit gap #18: smoke test — enabling every catalog widget at
        // once would crash if any partial references a missing route name,
        // an undefined variable, or a stale data-provider key.
        $this->admin->forceFill([
            'dashboard_widgets' => [
                'version' => DashboardWidgetCatalog::VERSION,
                'enabled' => array_keys(DashboardWidgetCatalog::all()),
            ],
        ])->save();

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk();
    }
}
