<?php

namespace Tests\Feature;

use App\Support\AutoLoginGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint B.3 — AUTO_LOGIN_ENABLED triple-gate.
 *
 * Auto-login must be blocked unless ALL THREE conditions are met:
 *   1. APP_ENV=local
 *   2. storage/app/.auto_login_enabled marker file exists
 *   3. config('app.auto_login_enabled') is true
 *
 * Missing any one of these blocks auto-login.
 */
class AutoLoginGuardTest extends TestCase
{
    use RefreshDatabase;

    protected string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->markerPath = storage_path('app/.auto_login_enabled');
        // Clean up any stale marker
        if (file_exists($this->markerPath)) {
            unlink($this->markerPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->markerPath)) {
            unlink($this->markerPath);
        }
        parent::tearDown();
    }

    /**
     * S-07: the middleware is now a thin reader of
     * `config('app.auto_login_verified')`, which is resolved once at boot
     * from AutoLoginGuard::resolve(). These tests drive the guard's
     * resolver directly so they can still mutate the three gate inputs
     * (env, config flag, marker file) and observe the decision.
     */
    private function callShouldAutoLogin(): bool
    {
        return AutoLoginGuard::resolve();
    }

    #[Test]
    public function auto_login_is_blocked_in_testing_environment(): void
    {
        // Tests run under APP_ENV=testing, so even with marker + config, it should fail.
        touch($this->markerPath);
        config(['app.auto_login_enabled' => true]);

        $this->assertFalse($this->callShouldAutoLogin(), 'Auto-login must NEVER fire outside APP_ENV=local');
    }

    #[Test]
    public function auto_login_is_blocked_without_marker_file(): void
    {
        // Simulate local env via reflection on the Application instance
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.auto_login_enabled' => true]);
        // no marker file

        $this->assertFalse($this->callShouldAutoLogin(), 'Auto-login requires the marker file');
    }

    #[Test]
    public function auto_login_is_blocked_without_config_flag(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        touch($this->markerPath);
        config(['app.auto_login_enabled' => false]);

        $this->assertFalse($this->callShouldAutoLogin(), 'Auto-login requires the config flag');
    }

    #[Test]
    public function auto_login_fires_only_with_all_three_gates_passed(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        touch($this->markerPath);
        config(['app.auto_login_enabled' => true]);

        $this->assertTrue($this->callShouldAutoLogin(), 'Auto-login should fire with all 3 gates passed');
    }

    #[Test]
    public function marker_file_is_gitignored(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));
        $this->assertStringContainsString(
            '.auto_login_enabled',
            $gitignore,
            'The auto-login marker file must be in .gitignore so it cannot be shipped'
        );
    }
}
