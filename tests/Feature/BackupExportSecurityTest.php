<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint B.2 — backup export sanitization.
 *
 * Verifies the downloaded backup JSON NEVER contains:
 *  - Password hashes (bcrypt strings starting with $2y$)
 *  - Invite tokens
 *  - Remember tokens
 *  - API tokens (hashed or plaintext)
 *  - Audit log data blobs (which can contain column before/after snapshots)
 *  - User records at all (explicitly excluded)
 */
class BackupExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password'),
            'invite_token' => 'sensitive-invite-token-should-never-leak',
            'remember_token' => 'remember-me-token-must-stay-secret',
        ]);
        $this->admin->assignRole('admin');
    }

    private function downloadBackup(): string
    {
        $response = $this->actingAs($this->admin)->get(route('management.backup'));
        $response->assertOk();

        // Capture streamed content
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    #[Test]
    public function backup_does_not_contain_password_hashes(): void
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $content = $this->downloadBackup();

        $this->assertStringNotContainsString('$2y$', $content, 'bcrypt hash leaked');
        $this->assertStringNotContainsString('"password"', $content, 'password field present');
    }

    #[Test]
    public function backup_does_not_contain_invite_tokens(): void
    {
        $content = $this->downloadBackup();

        $this->assertStringNotContainsString('sensitive-invite-token-should-never-leak', $content);
        $this->assertStringNotContainsString('"invite_token"', $content);
    }

    #[Test]
    public function backup_does_not_contain_remember_tokens(): void
    {
        $content = $this->downloadBackup();

        $this->assertStringNotContainsString('remember-me-token-must-stay-secret', $content);
        $this->assertStringNotContainsString('"remember_token"', $content);
    }

    #[Test]
    public function backup_does_not_contain_users_table(): void
    {
        $content = $this->downloadBackup();

        // Users are explicitly excluded from the backup by design.
        $this->assertStringNotContainsString('"users":[', $content);
    }

    #[Test]
    public function backup_does_not_contain_audit_logs(): void
    {
        $content = $this->downloadBackup();

        // Audit logs can contain before/after column snapshots, including sensitive data.
        $this->assertStringNotContainsString('"audit_logs":[', $content);
    }

    #[Test]
    public function backup_does_not_contain_api_tokens(): void
    {
        $content = $this->downloadBackup();

        $this->assertStringNotContainsString('"tenant_api_tokens":[', $content);
        $this->assertStringNotContainsString('"token_hash"', $content);
    }

    #[Test]
    public function backup_does_not_contain_payment_idempotency_keys(): void
    {
        // idempotency_key is derived from user_id and should not leak
        $content = $this->downloadBackup();

        $this->assertStringNotContainsString('"idempotency_key"', $content);
    }

    #[Test]
    public function backup_contains_customer_business_data(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Business Customer',
            'email' => 'customer@example.com',
        ]);

        $content = $this->downloadBackup();

        $this->assertStringContainsString('Test Business Customer', $content);
        $this->assertStringContainsString('"customers":[', $content);
    }

    #[Test]
    public function backup_includes_metadata_with_version(): void
    {
        $content = $this->downloadBackup();

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertEquals('2.0', $data['metadata']['version']);
        $this->assertArrayHasKey('note', $data['metadata']);
    }

    #[Test]
    public function unauthorized_user_cannot_download_backup(): void
    {
        $technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $technician->assignRole('technician');

        $this->actingAs($technician)
            ->get(route('management.backup'))
            ->assertForbidden();
    }
}
