<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // C1 (sprint 2026-04-24): Spatie's PermissionRegistrar caches
        // resolved roles and permissions in memory + Laravel's cache
        // store. When tests run in sequence with RefreshDatabase, the
        // DB rolls back between tests but the in-memory cache keeps
        // role/permission instances from the previous test, pointing
        // at IDs that no longer exist after the rollback. Forgetting
        // the cache + clearing the team id at the top of every test
        // makes each case start from zero.
        if ($this->app->bound(PermissionRegistrar::class)) {
            /** @var PermissionRegistrar $registrar */
            $registrar = $this->app->make(PermissionRegistrar::class);
            $registrar->forgetCachedPermissions();
            $registrar->setPermissionsTeamId(null);
        }
    }
}
