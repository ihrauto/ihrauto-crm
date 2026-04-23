<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-15 regression — quote → invoice conversion must copy items, mark
 * the quote as converted, and be idempotent on repeated calls.
 */
class QuoteToInvoiceConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_quote_items_to_invoice_items_and_marks_quote_converted(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Q2I Test',
            'phone' => '1',
        ]);

        $quote = Quote::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'quote_number' => 'QT-TEST-0001',
            'status' => 'accepted',
            'issue_date' => now(),
        ]);

        QuoteItem::create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Oil filter',
            'quantity' => 2,
            'unit_price' => 10,
            'tax_rate' => 8.1,
            'total' => 20,
        ]);
        QuoteItem::create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Labour',
            'quantity' => 1,
            'unit_price' => 50,
            'tax_rate' => 8.1,
            'total' => 50,
        ]);

        $invoice = app(InvoiceService::class)->createFromQuote($quote);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame($quote->id, $invoice->quote_id);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertCount(2, $invoice->items);
        $this->assertEquals(70.00, (float) $invoice->subtotal);

        $quote->refresh();
        $this->assertSame('converted', $quote->status);
    }

    public function test_second_conversion_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'name' => 'Idempotent', 'phone' => '2',
        ]);

        $quote = Quote::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'quote_number' => 'QT-TEST-0002',
            'status' => 'accepted',
            'issue_date' => now(),
        ]);
        QuoteItem::create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 8.1,
            'total' => 100,
        ]);

        $svc = app(InvoiceService::class);
        $first = $svc->createFromQuote($quote);
        $second = $svc->createFromQuote($quote->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::where('quote_id', $quote->id)->count());
    }

    public function test_rejects_conversion_when_quote_has_no_items(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'name' => 'Empty', 'phone' => '3',
        ]);

        $quote = Quote::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'quote_number' => 'QT-TEST-0003',
            'status' => 'draft',
            'issue_date' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InvoiceService::class)->createFromQuote($quote);
    }

    public function test_rejects_conversion_from_terminal_status(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'name' => 'Rejected', 'phone' => '4',
        ]);

        $quote = Quote::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'quote_number' => 'QT-TEST-0004',
            'status' => 'rejected',
            'issue_date' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InvoiceService::class)->createFromQuote($quote);
    }
}
