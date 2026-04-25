<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\DashboardWidgetCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardStudioControllerTest extends TestCase
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
    public function unauthenticated_user_is_redirected_to_login()
    {
        $this->get('/dashboard/studio')->assertRedirect('/login');
        $this->post('/dashboard/studio', ['keys' => []])->assertRedirect('/login');
        $this->post('/dashboard/studio/reset')->assertRedirect('/login');
    }

    #[Test]
    public function index_returns_catalog_and_enabled_keys()
    {
        $response = $this->actingAs($this->admin)->getJson('/dashboard/studio');

        $response->assertOk();
        $response->assertJsonStructure([
            'categories',
            'widgets',
            'enabled',
        ]);
        $response->assertJsonFragment(['operations' => 'Operations']);
    }

    #[Test]
    public function store_persists_a_clean_list()
    {
        $response = $this->actingAs($this->admin)->postJson('/dashboard/studio', [
            'keys' => ['active_jobs', 'pending_jobs'],
        ]);

        $response->assertOk();
        $response->assertJson(['enabled' => ['active_jobs', 'pending_jobs']]);

        $this->admin->refresh();
        $this->assertSame(['active_jobs', 'pending_jobs'], $this->admin->dashboard_widgets['enabled']);
    }

    #[Test]
    public function store_silently_drops_unknown_keys()
    {
        $response = $this->actingAs($this->admin)->postJson('/dashboard/studio', [
            'keys' => ['active_jobs', 'totally_fake_widget'],
        ]);

        $response->assertOk();
        $response->assertJsonMissing(['totally_fake_widget']);
        $response->assertJsonFragment(['enabled' => ['active_jobs']]);
    }

    #[Test]
    public function store_validates_payload_shape()
    {
        // Not an array
        $this->actingAs($this->admin)
            ->postJson('/dashboard/studio', ['keys' => 'not-an-array'])
            ->assertStatus(422);

        // Wrong key shape (uppercase, dash)
        $this->actingAs($this->admin)
            ->postJson('/dashboard/studio', ['keys' => ['Bad-Key-WITH-CAPS']])
            ->assertStatus(422);

        // Too many keys
        $bloat = array_fill(0, DashboardWidgetCatalog::MAX_KEYS + 5, 'a');
        $this->actingAs($this->admin)
            ->postJson('/dashboard/studio', ['keys' => $bloat])
            ->assertStatus(422);
    }

    #[Test]
    public function reset_clears_stored_preference()
    {
        $this->admin->forceFill([
            'dashboard_widgets' => ['version' => 1, 'enabled' => ['active_jobs']],
        ])->save();

        $response = $this->actingAs($this->admin)->postJson('/dashboard/studio/reset');

        $response->assertOk();
        $this->admin->refresh();
        $this->assertNull($this->admin->dashboard_widgets);
    }

    #[Test]
    public function user_cannot_mutate_another_users_dashboard_widgets()
    {
        // Audit gap T-9: lock the assumption that all studio writes
        // target the request user only. A regression that took a
        // user_id from the request body would silently let user A
        // overwrite user B's preferences across (or within) a tenant.
        $other = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        $other->assignRole('admin');
        $other->forceFill([
            'dashboard_widgets' => ['version' => 1, 'enabled' => ['active_jobs']],
        ])->save();

        $this->actingAs($this->admin)
            ->postJson('/dashboard/studio', ['keys' => ['pending_jobs', 'active_jobs', 'completed_today']])
            ->assertOk();

        $other->refresh();
        $this->assertSame(['active_jobs'], $other->dashboard_widgets['enabled']);
    }

    #[Test]
    public function reorder_persists_order_array()
    {
        $this->admin->forceFill([
            'dashboard_widgets' => ['version' => 1, 'enabled' => ['active_jobs', 'pending_jobs']],
        ])->save();

        $response = $this->actingAs($this->admin)->postJson('/dashboard/studio/reorder', [
            'order' => ['pending_jobs', 'active_jobs'],
        ]);

        $response->assertOk();
        $response->assertJson(['order' => ['pending_jobs', 'active_jobs']]);

        $this->admin->refresh();
        $this->assertSame(
            ['pending_jobs', 'active_jobs'],
            $this->admin->dashboard_widgets['order']
        );
    }

    #[Test]
    public function reorder_validates_payload_shape()
    {
        $this->actingAs($this->admin)
            ->postJson('/dashboard/studio/reorder', ['order' => 'not-an-array'])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson('/dashboard/studio/reorder', ['order' => ['Bad-Key']])
            ->assertStatus(422);
    }

    #[Test]
    public function store_is_rate_limited()
    {
        // 30 / minute. Hit it 31 times.
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->admin)
                ->postJson('/dashboard/studio', ['keys' => ['active_jobs']])
                ->assertOk();
        }

        $this->actingAs($this->admin)
            ->postJson('/dashboard/studio', ['keys' => ['active_jobs']])
            ->assertStatus(429);
    }
}
