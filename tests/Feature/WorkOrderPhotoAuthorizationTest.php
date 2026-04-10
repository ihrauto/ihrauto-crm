<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint B.7 — WorkOrderPhotoPolicy.
 *
 * Verifies:
 *   - Cross-tenant photo deletion is blocked
 *   - Non-uploader technicians cannot delete other technicians' photos
 *   - Admin/owner can delete any photo in their tenant
 *   - Deletion after completion requires admin (audit evidence protection)
 *   - Route param mismatch (wrong work order in URL) returns 404
 */
class WorkOrderPhotoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $adminA;

    protected User $technicianA;

    protected User $adminB;

    protected WorkOrder $workOrderA;

    protected WorkOrder $workOrderB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->adminA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->adminA->assignRole('admin');

        $this->technicianA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->technicianA->assignRole('technician');

        $this->adminB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->adminB->assignRole('admin');

        $customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
        $vehicleA = Vehicle::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $customerA->id,
        ]);
        $this->workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $customerA->id,
            'vehicle_id' => $vehicleA->id,
            'status' => 'in_progress',
        ]);

        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
        $vehicleB = Vehicle::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $customerB->id,
        ]);
        $this->workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $customerB->id,
            'vehicle_id' => $vehicleB->id,
            'status' => 'in_progress',
        ]);
    }

    private function createPhoto(WorkOrder $workOrder, User $uploader): WorkOrderPhoto
    {
        return WorkOrderPhoto::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'user_id' => $uploader->id,
            'filename' => 'test.jpg',
            'original_name' => 'test.jpg',
            'path' => 'work-order-photos/test.jpg',
            'type' => 'before',
        ]);
    }

    #[Test]
    public function tenant_a_user_cannot_delete_tenant_b_photo(): void
    {
        $photoB = $this->createPhoto($this->workOrderB, $this->adminB);

        $response = $this->actingAs($this->adminA)
            ->delete(route('work-orders.photos.destroy', [
                'workOrder' => $this->workOrderB,
                'photo' => $photoB,
            ]));

        $this->assertContains($response->status(), [403, 404]);
        $this->assertNotNull(
            WorkOrderPhoto::withoutGlobalScopes()->find($photoB->id),
            'Cross-tenant photo was deleted'
        );
    }

    #[Test]
    public function technician_without_delete_permission_is_blocked_at_route_level(): void
    {
        // The route is protected with permission:delete records middleware.
        // Standard technicians do not hold that permission, so their own photos
        // are also protected — deletion is an admin-only audit action.
        $photo = $this->createPhoto($this->workOrderA, $this->technicianA);

        $response = $this->actingAs($this->technicianA)
            ->delete(route('work-orders.photos.destroy', [
                'workOrder' => $this->workOrderA,
                'photo' => $photo,
            ]));

        $response->assertForbidden();
        $this->assertNotNull(WorkOrderPhoto::find($photo->id));
    }

    #[Test]
    public function technician_cannot_delete_other_technicians_photo(): void
    {
        $otherTech = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $otherTech->assignRole('technician');
        $photo = $this->createPhoto($this->workOrderA, $otherTech);

        $response = $this->actingAs($this->technicianA)
            ->delete(route('work-orders.photos.destroy', [
                'workOrder' => $this->workOrderA,
                'photo' => $photo,
            ]));

        $response->assertForbidden();
        $this->assertNotNull(WorkOrderPhoto::find($photo->id));
    }

    #[Test]
    public function admin_can_delete_any_photo_in_tenant(): void
    {
        $photo = $this->createPhoto($this->workOrderA, $this->technicianA);

        $response = $this->actingAs($this->adminA)
            ->delete(route('work-orders.photos.destroy', [
                'workOrder' => $this->workOrderA,
                'photo' => $photo,
            ]));

        $response->assertRedirect();
        $this->assertNull(WorkOrderPhoto::find($photo->id));
    }

    #[Test]
    public function technician_cannot_delete_photo_after_work_order_completion(): void
    {
        $photo = $this->createPhoto($this->workOrderA, $this->technicianA);
        $this->workOrderA->update(['status' => 'completed']);

        $response = $this->actingAs($this->technicianA)
            ->delete(route('work-orders.photos.destroy', [
                'workOrder' => $this->workOrderA,
                'photo' => $photo,
            ]));

        $response->assertForbidden();
        $this->assertNotNull(WorkOrderPhoto::find($photo->id), 'Audit photo was deleted after completion');
    }

    #[Test]
    public function admin_can_override_and_delete_photo_after_completion(): void
    {
        $photo = $this->createPhoto($this->workOrderA, $this->technicianA);
        $this->workOrderA->update(['status' => 'completed']);

        $response = $this->actingAs($this->adminA)
            ->delete(route('work-orders.photos.destroy', [
                'workOrder' => $this->workOrderA,
                'photo' => $photo,
            ]));

        $response->assertRedirect();
        $this->assertNull(WorkOrderPhoto::find($photo->id));
    }

    #[Test]
    public function route_param_mismatch_returns_404(): void
    {
        // Create a photo on workOrderA but pass workOrderB in the URL.
        $photo = $this->createPhoto($this->workOrderA, $this->technicianA);

        // Need a work order from the same tenant so TenantScope doesn't 404 first
        $otherWO = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->workOrderA->customer_id,
            'vehicle_id' => $this->workOrderA->vehicle_id,
        ]);

        $response = $this->actingAs($this->adminA)
            ->delete(route('work-orders.photos.destroy', [
                'workOrder' => $otherWO,
                'photo' => $photo,
            ]));

        $response->assertNotFound();
        $this->assertNotNull(WorkOrderPhoto::find($photo->id));
    }
}
