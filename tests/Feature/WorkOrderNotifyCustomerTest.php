<?php

namespace Tests\Feature;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkOrderNotifyCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create([
            'settings' => ['sms' => ['enabled' => true, 'from_number' => '+41441234567']],
        ]);
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('admin');

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41 79 555 12 34',
        ]);
        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $this->workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    #[Test]
    public function notify_endpoint_creates_a_communication_log(): void
    {
        // Mock Twilio so the test doesn't try a real network call.
        $messageObj = (object) ['sid' => 'SM_test_'.uniqid()];
        $messages = Mockery::mock();
        $messages->shouldReceive('create')->once()->andReturn($messageObj);
        $client = Mockery::mock(\Twilio\Rest\Client::class);
        $client->messages = $messages;
        $this->app->instance(\App\Services\SmsService::class, new \App\Services\SmsService($client));

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders.notify', $this->workOrder));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('communication_logs', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'status' => CommunicationLog::STATUS_QUEUED,
            'template' => 'work_order.ready',
            'user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function notify_endpoint_logs_skipped_when_tenant_disabled(): void
    {
        $this->tenant->update(['settings' => ['sms' => ['enabled' => false]]]);

        // Use real SmsService — no Twilio call expected.
        $response = $this->actingAs($this->admin)
            ->post(route('work-orders.notify', $this->workOrder));

        $response->assertRedirect();
        $response->assertSessionHas('info');

        $this->assertDatabaseHas('communication_logs', [
            'work_order_id' => $this->workOrder->id,
            'status' => CommunicationLog::STATUS_SKIPPED,
            'error_code' => 'tenant_disabled',
        ]);
    }

    #[Test]
    public function notify_endpoint_requires_authentication(): void
    {
        $response = $this->post(route('work-orders.notify', $this->workOrder));
        $response->assertRedirect('/login');
    }

    #[Test]
    public function notify_endpoint_blocks_cross_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherVehicle = Vehicle::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $otherWO = WorkOrder::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'vehicle_id' => $otherVehicle->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders.notify', $otherWO));

        // TenantScope returns 404 on cross-tenant route binding.
        $response->assertStatus(404);
    }

    #[Test]
    public function notify_endpoint_is_throttled(): void
    {
        $messages = Mockery::mock();
        $messages->shouldReceive('create')->andReturn((object) ['sid' => 'SM_x']);
        $client = Mockery::mock(\Twilio\Rest\Client::class);
        $client->messages = $messages;
        $this->app->instance(\App\Services\SmsService::class, new \App\Services\SmsService($client));

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->admin)
                ->post(route('work-orders.notify', $this->workOrder))
                ->assertRedirect();
        }
        $this->actingAs($this->admin)
            ->post(route('work-orders.notify', $this->workOrder))
            ->assertStatus(429);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
