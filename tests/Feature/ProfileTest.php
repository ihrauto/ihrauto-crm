<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user with a proper tenant for testing.
     */
    protected function createUserWithTenant(): User
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(14),
            'plan' => 'basic',
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_name_only_update_does_not_require_current_password(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Renamed',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $this->assertSame('Renamed', $user->refresh()->name);
    }

    public function test_email_change_requires_current_password(): void
    {
        $user = $this->createUserWithTenant();
        $originalEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                // current_password omitted
            ]);

        $response->assertSessionHasErrors('current_password')->assertRedirect('/profile');
        $this->assertSame($originalEmail, $user->refresh()->email);
    }

    public function test_email_change_rejects_wrong_current_password(): void
    {
        $user = $this->createUserWithTenant();
        $originalEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => 'not-the-password',
            ]);

        $response->assertSessionHasErrors('current_password')->assertRedirect('/profile');
        $this->assertSame($originalEmail, $user->refresh()->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
