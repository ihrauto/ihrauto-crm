<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected Customer $customer;

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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function user_can_view_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('invoices.show', $invoice));

        $response->assertStatus(200);
    }

    #[Test]
    public function draft_invoice_is_editable()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->assertTrue($invoice->isEditable());
        $this->assertTrue($invoice->isDraft());
        $this->assertFalse($invoice->isIssued());
    }

    #[Test]
    public function issued_invoice_is_not_editable()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $this->assertFalse($invoice->isEditable());
        $this->assertFalse($invoice->isDraft());
        $this->assertTrue($invoice->isIssued());
    }

    #[Test]
    public function invoice_balance_is_calculated_correctly()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => 500.00,
            'paid_amount' => 200.00,
        ]);

        $this->assertEquals(300.00, $invoice->balance);
    }

    #[Test]
    public function invoice_payment_status_shows_paid_when_fully_paid()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => 500.00,
            'paid_amount' => 500.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $this->assertEquals('paid', $invoice->payment_status);
    }

    #[Test]
    public function invoice_payment_status_shows_partial_when_partially_paid()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => 500.00,
            'paid_amount' => 200.00,
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $this->assertEquals('partial', $invoice->payment_status);
    }

    #[Test]
    public function void_invoice_requires_reason()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('invoices.void', $invoice), []);

        $response->assertSessionHasErrors(['void_reason']);
    }

    #[Test]
    public function can_void_unpaid_issued_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'paid_amount' => 0,
        ]);

        $this->assertTrue($invoice->canBeVoided());
    }

    #[Test]
    public function cannot_void_paid_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_PAID,
            'paid_amount' => 500.00,
            'total' => 500.00,
        ]);

        $this->assertFalse($invoice->canBeVoided());
    }

    /**
     * Bug review LOG-01 regression: in-memory `canBeVoided()` must reject
     * any non-zero paid_amount, even a value just above zero. Using a
     * non-persisted model sidesteps the decimal(10,2) DB column and tests
     * the comparison itself — the guarantee we care about is "the check
     * in the model matches the rest of the codebase's >0.01 convention",
     * independent of how the value got there.
     */
    #[Test]
    public function cannot_void_invoice_with_non_zero_paid_amount_in_memory()
    {
        $invoice = new Invoice;
        $invoice->status = Invoice::STATUS_ISSUED;
        $invoice->paid_amount = 0.05; // 5 rappen — the smallest Swiss-legal partial

        $this->assertFalse(
            $invoice->canBeVoided(),
            'An invoice with any partial payment must not be voidable.'
        );
    }

    /**
     * Bug review LOG-01 regression: string-cast zero values still void cleanly.
     */
    #[Test]
    public function can_void_invoice_with_string_zero_paid_amount()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'paid_amount' => '0.00', // some drivers return decimals as strings
            'total' => 500.00,
        ]);

        $this->assertTrue($invoice->canBeVoided());
    }

    #[Test]
    public function invoices_are_tenant_isolated()
    {
        // Create invoice for our tenant
        $ourInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-OUR-001',
        ]);

        // Create invoice for another tenant
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $theirInvoice = Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'invoice_number' => 'INV-THEIR-001',
        ]);

        // User should not be able to view other tenant's invoice
        // Returns 404 because tenant scope hides it (correct security behavior)
        $response = $this->actingAs($this->user)
            ->get(route('invoices.show', $theirInvoice));

        $response->assertNotFound();
    }

    #[Test]
    public function invoice_status_badge_color_is_correct()
    {
        $draftInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $paidInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_PAID,
        ]);

        $voidInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_VOID,
        ]);

        $this->assertStringContainsString('gray', $draftInvoice->status_badge_color);
        $this->assertStringContainsString('green', $paidInvoice->status_badge_color);
        $this->assertStringContainsString('red', $voidInvoice->status_badge_color);
    }
}
