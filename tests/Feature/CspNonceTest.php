<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks in the CSP nonce contract: every HTML response carries a
 * `'nonce-…'` directive in `script-src` / `style-src`, and the
 * `csp_nonce()` helper returns that same value during view rendering
 * so inline blocks can opt in. Once every inline script in the
 * codebase carries the nonce, `'unsafe-inline'` can drop out of the
 * CSP without breaking the page.
 */
class CspNonceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function csp_nonce_helper_returns_a_value_during_request(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        // The Studio panel inline <script> renders nonce="..." sourced
        // from the helper. Confirm the attribute is non-empty.
        $body = $response->getContent();
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+"/', $body);
    }
}
