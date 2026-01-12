<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company', // Required for tenant creation
        ]);

        $this->assertAuthenticated();

        // Check tenant was created
        $tenant = Tenant::where('name', 'Test Company')->first();
        $this->assertNotNull($tenant, 'Tenant should be created during registration');

        // Check user was created and linked to tenant
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($tenant->id, $user->tenant_id);

        // Check redirect (may be to dashboard, onboarding, or verify-email)
        $this->assertTrue(
            $response->isRedirect(),
            'Expected redirect after successful registration'
        );
    }
}
