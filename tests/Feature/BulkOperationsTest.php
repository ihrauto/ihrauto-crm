<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the bulk-issue invoices and bulk-status
 * work-order endpoints. Per-item failures must not corrupt siblings;
 * completion is explicitly forbidden from the bulk path.
 */
class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($this->tenant);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ]);
        $this->admin->assignRole('admin');
    }

    private function makeDraftInvoice(string $number, float $total = 100.00): Invoice
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => "C-{$number}",
            'phone' => $number,
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => $number,
            'customer_id' => $customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
            'paid_amount' => 0,
        ]);

        InvoiceItem::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => $total,
            'tax_rate' => 0,
            'total' => $total,
        ]);

        return $invoice;
    }

    public function test_bulk_issue_moves_drafts_to_issued_and_skips_non_drafts(): void
    {
        $draft1 = $this->makeDraftInvoice('INV-B-0001');
        $draft2 = $this->makeDraftInvoice('INV-B-0002');
        $alreadyIssued = $this->makeDraftInvoice('INV-B-0003');
        $alreadyIssued->forceFill([
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
            'locked_at' => now(),
        ])->save();

        $this->actingAs($this->admin)
            ->post(route('invoices.bulk-issue'), [
                'invoice_ids' => [$draft1->id, $draft2->id, $alreadyIssued->id],
            ])
            ->assertRedirect();

        $this->assertSame(Invoice::STATUS_ISSUED, $draft1->fresh()->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $draft2->fresh()->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $alreadyIssued->fresh()->status);
    }

    public function test_bulk_issue_validates_input(): void
    {
        $this->actingAs($this->admin)
            ->post(route('invoices.bulk-issue'), ['invoice_ids' => []])
            ->assertSessionHasErrors('invoice_ids');
    }

    public function test_bulk_status_change_moves_work_orders(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bulk WO', 'phone' => 'x',
        ]);
        $vehicle = Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'license_plate' => 'ZH-BULK-1',
            'make' => 'M', 'model' => 'Q', 'year' => 2020,
        ]);

        $wos = collect(range(1, 3))->map(fn ($i) => WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'created',
        ]));

        $this->actingAs($this->admin)
            ->post(route('work-orders.bulk-status'), [
                'work_order_ids' => $wos->pluck('id')->all(),
                'status' => 'in_progress',
            ])
            ->assertRedirect();

        foreach ($wos as $wo) {
            $this->assertSame('in_progress', $wo->fresh()->status);
        }
    }

    public function test_bulk_status_change_rejects_completion(): void
    {
        $this->actingAs($this->admin)
            ->post(route('work-orders.bulk-status'), [
                'work_order_ids' => [1],
                'status' => 'completed',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_bulk_status_change_is_atomic_on_illegal_transition(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Atomic', 'phone' => 'y',
        ]);
        $vehicle = Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'license_plate' => 'ZH-BULK-2',
            'make' => 'M', 'model' => 'Q', 'year' => 2020,
        ]);

        $ok = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'created',
        ]);
        // completed -> cancelled is NOT in ALLOWED_TRANSITIONS.
        $bad = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders.bulk-status'), [
                'work_order_ids' => [$ok->id, $bad->id],
                'status' => 'cancelled',
            ]);

        // The batch should fail, leaving both work orders unchanged.
        $this->assertSame('created', $ok->fresh()->status);
        $this->assertSame('completed', $bad->fresh()->status);
        $response->assertStatus(500);
    }
}
