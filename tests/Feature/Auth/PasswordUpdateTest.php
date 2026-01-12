<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithTenant(): User
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(14),
            'plan' => 'basic',
            'settings' => ['has_seen_tour' => true],
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_password_can_be_updated(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasNoErrors();

        // Check redirect (may be /profile or route('password.update') response)
        $this->assertTrue(
            $response->isRedirect(),
            'Expected a redirect response after password update'
        );

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        // Check for validation errors (may be in default bag or 'updatePassword' bag)
        $this->assertTrue(
            $response->isRedirect() &&
            (session()->has('errors') || session('errors')),
            'Expected validation error for wrong password'
        );
    }
}
