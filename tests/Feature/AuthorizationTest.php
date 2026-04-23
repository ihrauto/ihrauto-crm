<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceBay;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $technician;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->admin->assignRole('admin');

        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'technician',
        ]);
        $this->technician->assignRole('technician');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // --- Finance Controller Authorization ---

    #[Test]
    public function admin_can_access_finance_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('finance.index'));

        $response->assertStatus(200);
    }

    #[Test]
    public function receptionist_cannot_access_finance_page()
    {
        $receptionist = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'receptionist',
        ]);
        $receptionist->assignRole('receptionist');

        $response = $this->actingAs($receptionist)
            ->get(route('finance.index'));

        $response->assertStatus(403);
    }

    // --- Service Bay Authorization ---

    #[Test]
    public function admin_can_create_service_bay()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('work-bays.store'), [
                'name' => 'Test Bay',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_bays', ['name' => 'Test Bay']);
    }

    #[Test]
    public function technician_cannot_create_service_bay()
    {
        $response = $this->actingAs($this->technician)
            ->post(route('work-bays.store'), [
                'name' => 'Test Bay',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function technician_cannot_delete_service_bay()
    {
        $bay = ServiceBay::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bay To Delete',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->technician)
            ->delete(route('work-bays.destroy', $bay));

        $response->assertStatus(403);
    }

    // --- Product Authorization ---

    #[Test]
    public function user_can_create_product()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('products.store'), [
                'name' => 'Brake Pad',
                'price' => 45.50,
                'stock_quantity' => 10,
                'min_stock_quantity' => 5,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['name' => 'Brake Pad']);
    }

    // --- Cross-tenant isolation ---

    #[Test]
    public function user_cannot_access_other_tenant_product()
    {
        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Product',
            'price' => 10,
            'stock_quantity' => 5,
            'min_stock_quantity' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('products.update', $otherProduct), [
                'name' => 'Hacked Product',
                'price' => 0,
                'min_stock_quantity' => 0,
            ]);

        // Should fail - either 403 or 404 due to tenant scope
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    #[Test]
    public function user_cannot_access_other_tenant_service()
    {
        $otherTenant = Tenant::factory()->create();
        $otherService = Service::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Service',
            'price' => 100,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('services.update', $otherService), [
                'name' => 'Hacked Service',
                'price' => 0,
            ]);

        $this->assertTrue(in_array($response->status(), [403, 404]));
    }
}
