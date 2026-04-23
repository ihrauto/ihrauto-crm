<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-12 regression — rescheduling must preserve the original appointment's
 * duration, not swap in a 60-min default or compute a negative duration
 * from the new start and old end.
 */
class AppointmentRescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_preserves_original_duration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ]);
        $user->assignRole('admin');
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Reschedule Test',
            'phone' => '1',
        ]);

        $apt = Appointment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'start_time' => '2026-05-01 09:00',
            'end_time' => '2026-05-01 10:30', // 90 min
            'status' => 'scheduled',
            'type' => 'service',
            'title' => 'Oil change',
        ]);

        $this->actingAs($user);

        $response = $this->putJson("/ajax/appointments/{$apt->id}/reschedule", [
            'start' => '2026-05-02 14:00',
        ]);

        $response->assertOk();

        $apt->refresh();
        $this->assertSame('2026-05-02 14:00:00', $apt->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-02 15:30:00', $apt->end_time->format('Y-m-d H:i:s'));
    }
}
