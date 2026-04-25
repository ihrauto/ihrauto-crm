<?php

namespace Tests\Unit\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\SmsService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Customer $customer;

    protected Vehicle $vehicle;

    protected WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'settings' => ['sms' => ['enabled' => true, 'from_number' => '+41441234567']],
        ]);
        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '079 555 12 34',
            'sms_opt_out' => false,
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'make' => 'BMW',
            'model' => 'E90',
        ]);

        $this->workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function phoneFormats(): iterable
    {
        return [
            'CH national, leading 0' => ['079 555 12 34', 'CH', '+41795551234'],
            'CH national, no spaces' => ['0795551234', 'CH', '+41795551234'],
            'CH international, plus' => ['+41 79 555 12 34', 'CH', '+41795551234'],
            'CH international, 00' => ['0041 79 555 12 34', 'CH', '+41795551234'],
            'DE national' => ['0151 1234567', 'DE', '+491511234567'],
            'AT national' => ['0660 1234567', 'AT', '+436601234567'],
            'too short' => ['12', 'CH', null],
            'empty' => ['', 'CH', null],
            'letters only' => ['abc', 'CH', null],
            'unknown region, no prefix' => ['1234567', 'XX', null],
        ];
    }

    #[Test]
    #[DataProvider('phoneFormats')]
    public function e164_normalization(string $input, string $region, ?string $expected): void
    {
        $svc = new SmsService(null);
        $this->assertSame($expected, $svc->normalizeE164($input, $region));
    }

    #[Test]
    public function dispatch_skips_when_tenant_has_not_enabled_sms(): void
    {
        $this->tenant->update(['settings' => ['sms' => ['enabled' => false]]]);

        $svc = new SmsService(null);
        $log = $svc->sendWorkOrderReady($this->workOrder->fresh());

        $this->assertSame(CommunicationLog::STATUS_SKIPPED, $log->status);
        $this->assertSame('tenant_disabled', $log->error_code);
    }

    #[Test]
    public function dispatch_skips_when_customer_has_opted_out(): void
    {
        $this->customer->update(['sms_opt_out' => true]);

        $svc = new SmsService(null);
        $log = $svc->sendWorkOrderReady($this->workOrder->fresh());

        $this->assertSame(CommunicationLog::STATUS_SKIPPED, $log->status);
        $this->assertSame('opt_out', $log->error_code);
    }

    #[Test]
    public function dispatch_skips_when_customer_has_no_phone(): void
    {
        // customers.phone has a NOT NULL constraint at the schema level,
        // so simulate "no usable phone" by setting it to a value that
        // can't normalize to E.164 — same SmsService outcome.
        $this->customer->forceFill(['phone' => 'no-phone'])->save();

        $svc = new SmsService(null);
        $log = $svc->sendWorkOrderReady($this->workOrder->fresh());

        $this->assertSame(CommunicationLog::STATUS_SKIPPED, $log->status);
        $this->assertSame('no_phone', $log->error_code);
    }

    #[Test]
    public function dispatch_skips_when_twilio_credentials_missing(): void
    {
        config(['services.twilio.sid' => null, 'services.twilio.token' => null]);
        // Tenant from_number is set, but the client itself can't be built.
        $svc = new SmsService(null);
        $log = $svc->sendWorkOrderReady($this->workOrder->fresh());

        $this->assertSame(CommunicationLog::STATUS_SKIPPED, $log->status);
        $this->assertSame('not_configured', $log->error_code);
    }

    #[Test]
    public function dispatch_logs_queued_with_provider_id_on_success(): void
    {
        // Mock Twilio's $client->messages->create(...) chain.
        $messageObj = (object) ['sid' => 'SM_test_'.uniqid()];
        $messages = Mockery::mock();
        $messages->shouldReceive('create')
            ->once()
            ->with('+41795551234', Mockery::on(fn ($args) => isset($args['from']) && isset($args['body'])))
            ->andReturn($messageObj);
        $client = Mockery::mock(\Twilio\Rest\Client::class);
        $client->messages = $messages;

        $svc = new SmsService($client);
        $log = $svc->sendWorkOrderReady($this->workOrder->fresh());

        $this->assertSame(CommunicationLog::STATUS_QUEUED, $log->status);
        $this->assertSame($messageObj->sid, $log->provider_id);
        $this->assertSame('+41795551234', $log->to);
        $this->assertNotNull($log->sent_at);
        $this->assertStringContainsString($this->workOrder->customer->name, $log->body);
    }

    #[Test]
    public function dispatch_logs_failed_when_twilio_throws(): void
    {
        $messages = Mockery::mock();
        $messages->shouldReceive('create')->once()->andThrow(
            new \Twilio\Exceptions\TwilioException('Phone number not reachable', 21610)
        );
        $client = Mockery::mock(\Twilio\Rest\Client::class);
        $client->messages = $messages;

        $svc = new SmsService($client);
        $log = $svc->sendWorkOrderReady($this->workOrder->fresh());

        $this->assertSame(CommunicationLog::STATUS_FAILED, $log->status);
        $this->assertSame('21610', $log->error_code);
        $this->assertStringContainsString('Phone number not reachable', $log->error_message);
    }

    #[Test]
    public function communication_log_is_append_only(): void
    {
        $log = CommunicationLog::forceCreate([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'channel' => 'sms',
            'to' => '+41795551234',
            'template' => 'work_order.ready',
            'body' => 'test',
            'status' => CommunicationLog::STATUS_QUEUED,
        ]);

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
