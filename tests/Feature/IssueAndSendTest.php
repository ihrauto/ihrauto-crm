<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvoiceIssuedNotification;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regression tests for the Issue + Send flow:
 *   - drafts transition to issued
 *   - customer receives the InvoiceIssuedNotification
 *   - missing-email drafts return a friendly error (no state change)
 *   - the public signed URL resolves; tampering breaks it; drafts 404
 */
class IssueAndSendTest extends TestCase
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

    private function makeDraft(?string $email = 'client@example.com'): Invoice
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Signed Client',
            'phone' => '1',
            'email' => $email,
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-TEST-0001',
            'customer_id' => $customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100,
            'tax_total' => 8.10,
            'total' => 108.10,
            'paid_amount' => 0,
        ]);

        InvoiceItem::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 8.1,
            'total' => 100,
        ]);

        return $invoice;
    }

    public function test_issue_and_send_transitions_draft_and_notifies_customer(): void
    {
        Notification::fake();
        $invoice = $this->makeDraft();

        $this->actingAs($this->admin)
            ->post(route('invoices.issue-and-send', $invoice))
            ->assertRedirect();

        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->fresh()->status);

        Notification::assertSentTo(
            $invoice->customer->fresh(),
            InvoiceIssuedNotification::class,
        );
    }

    public function test_missing_customer_email_blocks_issue_and_send(): void
    {
        Notification::fake();
        $invoice = $this->makeDraft(email: null);

        $this->actingAs($this->admin)
            ->post(route('invoices.issue-and-send', $invoice))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_public_signed_url_renders_issued_invoice(): void
    {
        $invoice = $this->makeDraft();
        $invoice->forceFill([
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
            'locked_at' => now(),
        ])->save();

        $url = $invoice->publicPdfUrl();

        $this->get($url)
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    public function test_tampered_signed_url_is_rejected(): void
    {
        $invoice = $this->makeDraft();
        $invoice->forceFill([
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
            'locked_at' => now(),
        ])->save();

        $url = $invoice->publicPdfUrl();
        // Flip one character of the signature to simulate tampering.
        $tampered = preg_replace('/signature=([a-f0-9]+)/', 'signature=deadbeef', $url);

        $this->get($tampered)->assertStatus(403);
    }

    public function test_public_url_404s_for_draft_invoice(): void
    {
        // Force-generate a URL on a draft to simulate an out-of-band attempt.
        $invoice = $this->makeDraft();
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'invoices.public-pdf',
            now()->addDay(),
            ['token' => $invoice->publicPdfToken(), 'invoice' => $invoice->id],
        );

        $this->get($url)->assertStatus(404);
    }
}
