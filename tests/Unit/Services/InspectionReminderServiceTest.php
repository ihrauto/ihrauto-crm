<?php

namespace Tests\Unit\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\InspectionReminderService;
use App\Services\SmsService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InspectionReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'settings' => ['sms' => ['enabled' => true, 'from_number' => '+41441234567']],
        ]);
        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41 79 555 12 34',
        ]);
    }

    private function makeVehicle(int $daysOut, ?string $authority = 'MFK'): Vehicle
    {
        return Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'license_plate' => 'ZH '.fake()->unique()->numberBetween(10000, 99999),
            'make' => 'BMW',
            'model' => '320d',
            'next_inspection_at' => now()->addDays($daysOut)->toDateString(),
            'inspection_authority' => $authority,
        ]);
    }

    private function buildService(?int $expectedSendCount = null): InspectionReminderService
    {
        // Mock the Twilio chain so the SmsService returns queued logs
        // rather than actually hitting the network.
        $messages = Mockery::mock();
        $send = $messages->shouldReceive('create')->andReturn((object) ['sid' => 'SM_test_'.uniqid()]);
        if ($expectedSendCount !== null) {
            $send->times($expectedSendCount);
        }
        $client = Mockery::mock(\Twilio\Rest\Client::class);
        $client->messages = $messages;

        return new InspectionReminderService(new SmsService($client));
    }

    #[Test]
    public function resolves_30d_14d_and_3d_buckets(): void
    {
        $svc = $this->buildService(0);
        $today = Carbon::today();

        $this->assertSame('30d', $svc->resolveBucket($today->copy()->addDays(30), $today));
        $this->assertSame('14d', $svc->resolveBucket($today->copy()->addDays(14), $today));
        $this->assertSame('3d', $svc->resolveBucket($today->copy()->addDays(3), $today));
        $this->assertNull($svc->resolveBucket($today->copy()->addDays(20), $today));
        $this->assertNull($svc->resolveBucket($today->copy()->subDay(), $today));
    }

    #[Test]
    public function sends_sms_when_vehicle_lands_in_a_bucket(): void
    {
        $vehicle = $this->makeVehicle(daysOut: 30);
        // Debug: confirm vehicle persisted with next_inspection_at.
        $this->assertNotNull($vehicle->fresh()->next_inspection_at, 'Vehicle next_inspection_at must be set.');
        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $vehicle->fresh()->next_inspection_at->toDateString(),
        );

        $svc = $this->buildService(1);

        $results = $svc->sendDue();

        $this->assertCount(1, $results);
        $this->assertSame(CommunicationLog::STATUS_QUEUED, $results->first()['status']);

        $this->assertDatabaseHas('communication_logs', [
            'tenant_id' => $this->tenant->id,
            'template' => 'inspection.reminder.30d',
            'status' => CommunicationLog::STATUS_QUEUED,
        ]);
    }

    #[Test]
    public function is_idempotent_for_the_same_due_date_and_bucket(): void
    {
        $vehicle = $this->makeVehicle(daysOut: 30);
        $svc = $this->buildService(1); // expect EXACTLY one Twilio call

        $first = $svc->sendDue();
        $second = $svc->sendDue();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second, 'Second run on the same day must be a no-op.');
    }

    #[Test]
    public function bucket_resets_when_due_date_changes(): void
    {
        $vehicle = $this->makeVehicle(daysOut: 30);
        $svc = $this->buildService(2);

        $first = $svc->sendDue();
        $this->assertCount(1, $first);

        // Customer passes inspection — operator updates the due date.
        // The 30d bucket for the NEW due date should fire on its own.
        $vehicle->forceFill([
            'next_inspection_at' => now()->copy()->addDays(30)->addYear()->toDateString(),
        ])->save();

        // Re-running today won't match (the new due date is way in the
        // future). Move the clock to its 30d bucket.
        $svc->sendDue(now()->copy()->addYear());

        $this->assertSame(2, CommunicationLog::where('tenant_id', $this->tenant->id)
            ->where('template', 'like', 'inspection.reminder.%')->count());
    }

    #[Test]
    public function copy_uses_country_authority_label(): void
    {
        $this->makeVehicle(daysOut: 14, authority: 'TUV');
        $svc = $this->buildService(1);

        $svc->sendDue();

        $log = CommunicationLog::where('template', 'inspection.reminder.14d')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('TÜV', $log->body);
    }

    #[Test]
    public function ignores_vehicles_outside_any_bucket_window(): void
    {
        $this->makeVehicle(daysOut: 20);
        $this->makeVehicle(daysOut: 7);
        $svc = $this->buildService(0);

        $results = $svc->sendDue();
        $this->assertCount(0, $results);
    }

    #[Test]
    public function records_sent_bucket_even_when_send_was_skipped(): void
    {
        // Customer opted out → SMS skipped, but we still mark the bucket
        // as attempted so the daily run doesn't keep re-trying.
        $this->customer->update(['sms_opt_out' => true]);
        $vehicle = $this->makeVehicle(daysOut: 30);

        $svc = $this->buildService(0);
        $results = $svc->sendDue();

        $this->assertCount(1, $results);
        $this->assertSame(CommunicationLog::STATUS_SKIPPED, $results->first()['status']);

        $vehicle->refresh();
        $this->assertNotEmpty($vehicle->inspection_reminders_sent);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
