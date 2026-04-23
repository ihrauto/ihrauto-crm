<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\FinanceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug review LOG-02 regression.
 *
 * FinanceService::getMonthlyRevenue() used to call `TO_CHAR(...)`, which is
 * Postgres-only. SQLite (our CI driver) threw `no such function: TO_CHAR`
 * and the test suite never exercised this path, so the bug was dormant.
 *
 * This test exercises the exact SQL path under the test DB driver. If
 * anyone reintroduces a Postgres-only fragment in this code, the CI
 * build fails here immediately.
 */
class FinanceServiceMonthlyRevenuePortabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FinanceService $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->finance = new FinanceService;
        Cache::flush();
    }

    #[Test]
    public function get_monthly_revenue_runs_under_test_db_driver(): void
    {
        // Seed two payments in two different months.
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'amount' => 250,
            'method' => 'cash',
            'payment_date' => now()->startOfMonth(),
        ]);
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'amount' => 125,
            'method' => 'cash',
            'payment_date' => now()->subMonth()->startOfMonth(),
        ]);

        // The assertion we care about is "this call does not throw".
        // Before LOG-02 the call would explode with a SQL syntax error
        // under the SQLite test driver.
        $result = $this->finance->getMonthlyRevenue(12);

        $this->assertIsArray($result);
        $this->assertCount(12, $result, 'Should return one entry per requested month, padding zeros.');

        // At least two months should have revenue > 0.
        $monthsWithRevenue = array_filter($result, fn ($row) => $row['revenue'] > 0);
        $this->assertGreaterThanOrEqual(
            2,
            count($monthsWithRevenue),
            'Both seeded payments should land in a revenue-bearing month.'
        );
    }
}
