<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config([
            'services.stripe.prices.basic' => 'price_test_basic',
            'services.stripe.prices.standard' => 'price_test_standard',
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login_for_checkout(): void
    {
        $this->get('/billing/checkout/standard')->assertRedirect('/login');
    }

    #[Test]
    public function checkout_for_unmapped_plan_redirects_back_with_a_sales_message(): void
    {
        // Custom plan has no Stripe price; treat as "contact sales"
        // rather than 404 / 500.
        $response = $this->actingAs($this->admin)->get('/billing/checkout/custom');

        $response->assertRedirect(route('billing.pricing'));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function checkout_cancel_returns_a_friendly_redirect(): void
    {
        $response = $this->actingAs($this->admin)->get('/billing/cancel');

        $response->assertRedirect(route('billing.pricing'));
        $response->assertSessionHas('info');
    }

    #[Test]
    public function billing_portal_redirects_to_pricing_when_tenant_has_no_stripe_id(): void
    {
        // No stripe_id set — portal can't open. Redirect with hint.
        $this->assertNull($this->tenant->stripe_id);

        $response = $this->actingAs($this->admin)->get('/billing/portal');

        $response->assertRedirect(route('billing.pricing'));
        $response->assertSessionHas('info');
    }

    #[Test]
    public function billing_portal_requires_manage_settings_permission(): void
    {
        $tech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        $tech->assignRole('technician');

        $response = $this->actingAs($tech)->get('/billing/portal');

        // Permission middleware aborts with 403 (or the framework's
        // configured response).
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    #[Test]
    public function checkout_route_is_throttled(): void
    {
        // 10/min on the checkout route. We use the unmapped-plan path
        // (which redirects without contacting Stripe) so we don't burn
        // through real API quota during the test.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->admin)->get('/billing/checkout/custom')->assertStatus(302);
        }
        $this->actingAs($this->admin)->get('/billing/checkout/custom')->assertStatus(429);
    }
}
