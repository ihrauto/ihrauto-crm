<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Scalability C-3 regression — the audit-log archival command must:
 *   - move rows older than the retention cutoff into audit_logs_archive
 *   - leave recent rows untouched in audit_logs
 *   - preserve the original id, tenant_id, user_id, model references
 *   - stamp archived_at on each moved row
 *   - be safe in --dry-run (no writes)
 */
class AuditLogArchivalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
    }

    /**
     * Insert an audit_logs row with a specific `created_at`. Uses raw
     * insert so we can backdate without touching the Auditable trait.
     */
    private function insertAuditLog(\Carbon\Carbon $createdAt): int
    {
        return DB::table('audit_logs')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'action' => 'updated',
            'model_type' => 'App\\Models\\Invoice',
            'model_id' => '1',
            'changes' => json_encode(['foo' => 'bar']),
            'ip_address' => '127.0.0.1',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_moves_old_rows_to_archive_and_deletes_from_live(): void
    {
        $oldId = $this->insertAuditLog(now()->subYears(3));
        $recentId = $this->insertAuditLog(now()->subDays(10));

        $this->artisan('audit-logs:archive', ['--days' => 730, '--chunk' => 100])
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $oldId]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recentId]);

        $this->assertDatabaseHas('audit_logs_archive', [
            'id' => $oldId,
            'tenant_id' => $this->tenant->id,
            'model_type' => 'App\\Models\\Invoice',
        ]);

        // archived_at was stamped.
        $archived = DB::table('audit_logs_archive')->where('id', $oldId)->first();
        $this->assertNotNull($archived->archived_at);
    }

    public function test_dry_run_does_not_move_anything(): void
    {
        $oldId = $this->insertAuditLog(now()->subYears(3));

        $this->artisan('audit-logs:archive', [
            '--days' => 730,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $oldId]);
        $this->assertDatabaseMissing('audit_logs_archive', ['id' => $oldId]);
    }

    public function test_noop_when_nothing_to_archive(): void
    {
        $this->insertAuditLog(now()->subDays(10));

        $this->artisan('audit-logs:archive', ['--days' => 730])
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();
    }

    public function test_rejects_unsafe_days_argument(): void
    {
        // Command clamps --days to >=30 to prevent accidentally archiving
        // yesterday's rows.
        $recent = $this->insertAuditLog(now()->subDays(5));

        $this->artisan('audit-logs:archive', ['--days' => 1])
            ->assertSuccessful();

        // 5-day-old row should still be in live because --days was clamped to 30.
        $this->assertDatabaseHas('audit_logs', ['id' => $recent]);
    }
}
