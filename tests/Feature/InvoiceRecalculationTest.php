<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-07 regression — invoice totals must be server-authoritative. A
 * tampered line total is rewritten from (quantity × unit_price) on
 * recalculate().
 */
class InvoiceRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculate_rewrites_tampered_line_totals(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Line Test',
            'phone' => '1',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-TEST-0001',
            'customer_id' => $customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now(),
        ]);

        // Simulate a tampered line: qty=2, price=10 (expected total=20) but
        // someone stored total=5 (negative discount? bug? malicious API?).
        $item = InvoiceItem::create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'description' => 'Oil filter',
            'quantity' => 2,
            'unit_price' => 10.00,
            'tax_rate' => 8.1,
            'total' => 5.00, // WRONG
        ]);

        $invoice->recalculate();

        $item->refresh();
        $invoice->refresh();

        $this->assertEquals(20.00, (float) $item->total, 'Line total must be recomputed from qty × unit_price.');
        $this->assertEquals(20.00, (float) $invoice->subtotal);
        $this->assertEquals(round(20.00 * 0.081, 2), (float) $invoice->tax_total);
    }
}
