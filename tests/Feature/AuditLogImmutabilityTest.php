<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DATA-04 (sprint 2026-04-24): compliance audit rows must be immutable
 * through Eloquent. Notes (action='note') are exempted because the
 * super-admin UI uses Eloquent to edit / delete them.
 *
 * The archive path (`audit-logs:archive`) uses the query builder and is
 * NOT routed through Eloquent events — covered by its own existing
 * AuditLogArchivalTest, not this one.
 */
class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function compliance_audit_row_cannot_be_deleted_via_eloquent(): void
    {
        $log = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->actor->id,
            'action' => 'tenant.suspend',
            'model_type' => Tenant::class,
            'model_id' => $this->tenant->id,
            'changes' => ['reason' => 'test'],
            'ip_address' => '127.0.0.1',
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/compliance data.*cannot be deleted/');

        $log->delete();
    }

    #[Test]
    public function administrative_note_row_can_be_deleted_via_eloquent(): void
    {
        $note = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->actor->id,
            'action' => 'note',
            'model_type' => Tenant::class,
            'model_id' => $this->tenant->id,
            'changes' => ['content' => 'Internal remark'],
            'ip_address' => '127.0.0.1',
        ]);

        $note->delete();

        $this->assertDatabaseMissing('audit_logs', ['id' => $note->id]);
    }

    #[Test]
    public function archival_query_builder_path_is_unaffected_by_the_guard(): void
    {
        // The ArchiveAuditLogsCommand uses DB::table('audit_logs')->whereIn(...)
        // ->delete(), which bypasses Eloquent events. This test confirms the
        // guard doesn't interfere with that path — otherwise we'd lose the
        // only way to move cold rows into audit_logs_archive.
        $log = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->actor->id,
            'action' => 'tenant.archive',
            'model_type' => Tenant::class,
            'model_id' => $this->tenant->id,
            'changes' => ['by' => 'system'],
            'ip_address' => '127.0.0.1',
        ]);

        $deleted = DB::table('audit_logs')->where('id', $log->id)->delete();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('audit_logs', ['id' => $log->id]);
    }
}
