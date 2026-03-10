<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSurfaceHardeningTest extends TestCase
{
    public function test_root_route_serves_pricing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('pricing');
    }

    public function test_removed_debug_and_setup_routes_are_not_publicly_available(): void
    {
        foreach ([
            '/setup-admin',
            '/cleanup-test-data',
            '/restore-super-admin',
            '/debug-registration',
            '/debug-dashboard',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
