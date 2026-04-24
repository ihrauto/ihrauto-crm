<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->user->assignRole('admin');
    }

    #[Test]
    public function user_can_view_customers_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.index');
    }

    #[Test]
    public function user_can_create_customer()
    {
        $customerData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+41791234567',
            'address' => '123 Main Street',
            'city' => 'Zurich',
            'postal_code' => '8000',
            'country' => 'CH',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertRedirect();

        // DATA-03: email is encrypted at rest — assert via name + the
        // deterministic email_hash, then confirm the decrypted value.
        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'email_hash' => Customer::lookupEmailHash('john.doe@example.com'),
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertSame('john.doe@example.com', Customer::where('name', 'John Doe')->first()->email);
    }

    #[Test]
    public function user_can_view_single_customer()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.show', $customer));

        $response->assertStatus(200);
        $response->assertViewIs('customers.show');
    }

    #[Test]
    public function user_can_update_customer()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name Surname',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('customers.update', $customer), [
                'name' => 'New Name Surname',
                'email' => 'new@example.com',
                'phone' => '+41799999999',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Name Surname',
        ]);
    }

    #[Test]
    public function customer_search_is_case_insensitive()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hans Mueller',
            'email' => 'hans@example.com',
        ]);

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Peter Schmidt',
            'email' => 'peter@example.com',
        ]);

        // Search lowercase should find uppercase
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'mueller']));

        $response->assertStatus(200);
        $response->assertSee('Hans Mueller');
        $response->assertDontSee('Peter Schmidt');
    }

    #[Test]
    public function customer_search_works_with_email()
    {
        // DATA-03: email is encrypted at rest, so partial `LIKE` search
        // is no longer possible on that column. The admin customer
        // search matches an email via the deterministic `email_hash`
        // column — partial strings won't hit; the full email value
        // (case-insensitive, trimmed) does.
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'email' => 'unique.test@example.com',
        ]);

        // Full email hits via email_hash.
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'unique.test@example.com']));
        $response->assertStatus(200);
        $response->assertSee('Test Customer');

        // Case-insensitive still hits (hash is over lowercased value).
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'Unique.Test@Example.com']));
        $response->assertSee('Test Customer');

        // Partial (legacy LIKE) does NOT hit — documented DATA-03 change.
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'unique.test']));
        $response->assertDontSee('Test Customer');
    }

    #[Test]
    public function customers_are_tenant_isolated()
    {
        // Create customer for our tenant
        $ourCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Our Customer',
        ]);

        // Create customer for another tenant
        $otherTenant = Tenant::factory()->create();
        $theirCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Their Customer',
        ]);

        // User should not see other tenant's customer in index
        $response = $this->actingAs($this->user)
            ->get(route('customers.index'));

        $response->assertSee('Our Customer');
        $response->assertDontSee('Their Customer');
    }

    #[Test]
    public function customer_has_vehicles_relationship()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertTrue($customer->vehicles->contains($vehicle));
        $this->assertCount(1, $customer->vehicles);
    }

    #[Test]
    public function customer_full_name_attribute_returns_name()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Max Mustermann',
        ]);

        $this->assertEquals('Max Mustermann', $customer->full_name);
    }

    #[Test]
    public function customer_api_show_returns_json()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('tenant.ajax.customers.show', $customer));

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name', 'email', 'phone']);
    }

    #[Test]
    public function customer_history_returns_json()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('tenant.ajax.customer.history', $customer));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'customer',
            'checkins',
            'tires',
            'vehicles',
            'summary',
        ]);
    }
}
