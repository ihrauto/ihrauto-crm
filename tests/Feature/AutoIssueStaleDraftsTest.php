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
 * Regression tests for AutoIssueStaleDraftsCommand:
 *   - skips tenants that haven't opted in
 *   - issues drafts older than the threshold
 *   - leaves fresh drafts alone
 *   - --dry-run changes no rows
 */
class AutoIssueStaleDraftsTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraft(Tenant $tenant, \Carbon\Carbon $updatedAt): Invoice
    {
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'AutoIssue Test',
            'phone' => (string) random_int(1000, 9999),
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-AI-'.$customer->id,
            'customer_id' => $customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => $updatedAt->copy()->toDateString(),
            'due_date' => $updatedAt->copy()->addDays(30)->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
            'paid_amount' => 0,
        ]);

        InvoiceItem::create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
            'total' => 100,
        ]);

        // Force the updated_at timestamp so the age filter has something
        // to match against. Eloquent's automatic timestamping would
        // otherwise set updated_at to now().
        $invoice->forceFill(['updated_at' => $updatedAt])->save();

        return $invoice;
    }

    public function test_opted_out_tenant_leaves_drafts_alone(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'settings' => ['auto_issue_drafts_after_days' => 0],
        ]);
        app(TenantContext::class)->set($tenant);

        $draft = $this->makeDraft($tenant, now()->subDays(30));

        $this->artisan('invoices:auto-issue-stale')->assertSuccessful();

        $this->assertSame(Invoice::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_issues_drafts_older_than_threshold(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'settings' => ['auto_issue_drafts_after_days' => 7],
        ]);
        app(TenantContext::class)->set($tenant);

        $stale = $this->makeDraft($tenant, now()->subDays(10));
        $fresh = $this->makeDraft($tenant, now()->subDays(2));

        $this->artisan('invoices:auto-issue-stale')->assertSuccessful();

        $this->assertSame(Invoice::STATUS_ISSUED, $stale->fresh()->status);
        $this->assertSame(Invoice::STATUS_DRAFT, $fresh->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'settings' => ['auto_issue_drafts_after_days' => 3],
        ]);
        app(TenantContext::class)->set($tenant);

        $stale = $this->makeDraft($tenant, now()->subDays(5));

        $this->artisan('invoices:auto-issue-stale', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(Invoice::STATUS_DRAFT, $stale->fresh()->status);
    }
}
