<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * H-3: /subscription/setup rewrites tenant billing details (IBAN, bank,
 * company email, tax rate). Before this fix, any authenticated tenant
 * user could call it, enabling a technician to redirect invoices to an
 * attacker-controlled IBAN. These tests lock in that only the
 * `manage settings` permission unlocks the endpoint.
 */
class SubscriptionSetupAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(14),
            'plan' => 'basic',
        ]);
    }

    #[Test]
    public function technician_cannot_rewrite_tenant_billing_settings(): void
    {
        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'technician',
            'email_verified_at' => now(),
        ]);
        $technician->assignRole('technician');

        $response = $this->actingAs($technician)->post('/subscription/setup', [
            'company_name' => 'Attacker GmbH',
            'currency' => 'EUR',
            'tax_rate' => 0,
            'iban' => 'CH00 ATTACKER IBAN',
        ]);

        $response->assertForbidden();

        $this->tenant->refresh();
        $this->assertNotSame('Attacker GmbH', $this->tenant->name);
        $settings = $this->tenant->settings ?? [];
        $this->assertNotSame('CH00 ATTACKER IBAN', $settings['iban'] ?? null);
    }

    #[Test]
    public function admin_can_rewrite_tenant_billing_settings(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post('/subscription/setup', [
            'company_name' => 'Legit Workshop AG',
            'phone' => '+41000000000',
            'email' => 'legit-'.uniqid().'@example.com',
            'address' => 'Teststrasse 1',
            'city' => 'Zurich',
            'currency' => 'EUR',
            'tax_rate' => 8.1,
            'bank_name' => 'Test Bank',
            'iban' => 'CH00 LEGIT IBAN',
        ]);

        $response->assertOk();

        $this->tenant->refresh();
        $this->assertSame('Legit Workshop AG', $this->tenant->name);
        $settings = $this->tenant->settings ?? [];
        $this->assertSame('CH00 LEGIT IBAN', $settings['iban'] ?? null);
    }
}
