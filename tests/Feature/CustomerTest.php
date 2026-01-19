<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function user_can_view_customers_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.index');
    }

    /** @test */
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

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
    public function customer_search_works_with_email()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'email' => 'unique.test@example.com',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'unique.test']));

        $response->assertStatus(200);
        $response->assertSee('Test Customer');
    }

    /** @test */
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

    /** @test */
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

    /** @test */
    public function customer_full_name_attribute_returns_name()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Max Mustermann',
        ]);

        $this->assertEquals('Max Mustermann', $customer->full_name);
    }

    /** @test */
    public function customer_api_show_returns_json()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('api.customers.show', $customer));

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name', 'email', 'phone']);
    }

    /** @test */
    public function customer_history_returns_json()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('api.customer.history', $customer));

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
