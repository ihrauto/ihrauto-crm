<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Tenant $tenant;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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
