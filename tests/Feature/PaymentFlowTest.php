<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for payment recording, sync, overpayment prevention, and void reversal.
 */
class PaymentFlowTest extends TestCase
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
    public function can_record_full_payment_on_issued_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 500.00,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(500.00, (float) $invoice->paid_amount);
    }

    #[Test]
    public function partial_payment_sets_partial_status()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 500.00,
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 200.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PARTIAL, $invoice->status);
        $this->assertEquals(200.00, (float) $invoice->paid_amount);
    }

    #[Test]
    public function overpayment_is_rejected()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 100.00,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 150.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // No payment should have been created
        $this->assertCount(0, Payment::all());
    }

    #[Test]
    public function cannot_pay_draft_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'total' => 100.00,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function cannot_pay_void_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_VOID,
            'total' => 100.00,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function duplicate_payment_is_prevented_via_idempotency_key()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 500.00,
            'paid_amount' => 0,
        ]);

        $paymentData = [
            'invoice_id' => $invoice->id,
            'amount' => 250.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
            'idempotency_key' => 'idem-unique-123',
        ];

        // First payment should succeed
        $this->actingAs($this->user)->post(route('payments.store'), $paymentData);
        $this->assertCount(1, Payment::all());

        // Second identical payment should be blocked by idempotency
        $this->actingAs($this->user)->post(route('payments.store'), $paymentData);
        $this->assertCount(1, Payment::all());
    }

    #[Test]
    public function duplicate_payment_is_prevented_when_no_idempotency_key_is_provided()
    {
        // Regression test for Sprint A.5 — previously, a payment submitted without
        // an idempotency_key or transaction_reference would skip the duplicate check
        // entirely. A browser back-button + resubmit could record the same payment twice.
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 500.00,
            'paid_amount' => 0,
        ]);

        $paymentData = [
            'invoice_id' => $invoice->id,
            'amount' => 250.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
            // NOTE: no idempotency_key, no transaction_reference
        ];

        // First submission succeeds
        $this->actingAs($this->user)->post(route('payments.store'), $paymentData);
        $this->assertCount(1, Payment::all());

        // Second identical submission must be blocked
        $this->actingAs($this->user)->post(route('payments.store'), $paymentData);
        $this->assertCount(1, Payment::all(), 'Duplicate payment was recorded without idempotency key');

        // The stored payment should have a derived idempotency key (not null)
        $payment = Payment::first();
        $this->assertNotNull($payment->idempotency_key);
        $this->assertEquals(64, strlen($payment->idempotency_key), 'Derived key should be a SHA256 hex string');
    }

    #[Test]
    public function derived_idempotency_key_includes_tenant_id()
    {
        // B-LOGIC-01 (2026-04-24 review): the fallback idempotency hash must
        // fold `tenant_id` into its inputs so two tenants with shape-identical
        // requests can never collide in the idempotency cache. Verified here
        // by reconstructing the expected hash from the payment's stored fields
        // (including the tenant_id) and asserting it matches — and that the
        // same formula WITHOUT tenant_id does NOT match.
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 100.00,
            'paid_amount' => 0,
        ]);

        $paymentDate = now()->toDateString();
        $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'method' => 'cash',
            'payment_date' => $paymentDate,
            // No idempotency_key / transaction_reference — forces derivation.
        ]);

        $payment = Payment::first();
        $this->assertNotNull($payment, 'Payment not created');
        $this->assertNotNull($payment->idempotency_key);

        $expectedWithTenant = hash('sha256', implode('|', [
            'payment',
            (string) $this->tenant->id,
            (string) $invoice->id,
            '100.00',
            $paymentDate,
            'cash',
            (string) $this->user->id,
        ]));
        $expectedWithoutTenant = hash('sha256', implode('|', [
            'payment',
            (string) $invoice->id,
            '100.00',
            $paymentDate,
            'cash',
            (string) $this->user->id,
        ]));

        $this->assertSame($expectedWithTenant, $payment->idempotency_key,
            'Derived idempotency key must match the tenant-inclusive hash.');
        $this->assertNotSame($expectedWithoutTenant, $payment->idempotency_key,
            'Derived idempotency key must NOT match the pre-fix hash without tenant_id.');
    }

    #[Test]
    public function different_payments_on_same_invoice_are_both_accepted(): void
    {
        // Verify the fallback key is distinctive enough that legitimate separate
        // payments (e.g., partial + final) aren't blocked.
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 500.00,
            'paid_amount' => 0,
        ]);

        // First: 200 in cash
        $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 200.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        // Second: different amount — must be accepted as distinct
        $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 300.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertCount(2, Payment::all());
    }

    #[Test]
    public function duplicate_payment_is_prevented_when_only_transaction_reference_is_sent()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 500.00,
            'paid_amount' => 0,
        ]);

        $paymentData = [
            'invoice_id' => $invoice->id,
            'amount' => 250.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
            'transaction_reference' => 'TXN-UNIQUE-123',
        ];

        $this->actingAs($this->user)->post(route('payments.store'), $paymentData);
        $this->actingAs($this->user)->post(route('payments.store'), $paymentData);

        $this->assertCount(1, Payment::all());
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'idempotency_key' => 'TXN-UNIQUE-123',
        ]);
    }

    #[Test]
    public function void_invoice_service_sets_correct_status()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 300.00,
            'paid_amount' => 0,
        ]);

        $invoiceService = app(InvoiceService::class);
        $voided = $invoiceService->voidInvoice($invoice, 'Customer cancelled');

        $this->assertEquals(Invoice::STATUS_VOID, $voided->status);
        $this->assertEquals('Customer cancelled', $voided->void_reason);
        $this->assertNotNull($voided->voided_at);
    }

    #[Test]
    public function void_invoice_is_idempotent()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_VOID,
            'total' => 300.00,
            'paid_amount' => 0,
        ]);

        $invoiceService = app(InvoiceService::class);
        $result = $invoiceService->voidInvoice($invoice, 'Duplicate void');

        // Should return the same invoice without error
        $this->assertEquals(Invoice::STATUS_VOID, $result->status);
    }

    #[Test]
    public function payment_delete_is_rejected(): void
    {
        // D.12 — payments are immutable financial records.
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 100.00,
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $payment = Payment::first();
        $this->assertNotNull($payment);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('immutable financial records');

        $payment->delete();
    }

    #[Test]
    public function payment_force_delete_is_rejected(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total' => 100.00,
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->user)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $payment = Payment::first();

        $this->expectException(\LogicException::class);

        $payment->forceDelete();
    }
}
