<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                // L-1: password rule requires 12+ chars, mixed case, numbers.
                'password' => 'CompliantPass12',
                'password_confirmation' => 'CompliantPass12',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    /**
     * H-4: the response for a known vs. unknown email must be indistinguishable.
     * Before the fix, RESET_LINK_SENT produced a flash `status`, while
     * INVALID_USER surfaced an error on the `email` field — a free account
     * enumeration oracle.
     */
    public function test_unknown_email_gets_same_response_as_known_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'known@example.com']);

        $knownResponse = $this
            ->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'known@example.com']);

        $unknownResponse = $this
            ->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'no-such-user@example.com']);

        // Both branches redirect back with the same flash status, and
        // neither surfaces an `email` error.
        $knownResponse->assertRedirect('/forgot-password')->assertSessionHas('status');
        $unknownResponse->assertRedirect('/forgot-password')->assertSessionHas('status');

        $knownResponse->assertSessionMissing('errors.email');
        $unknownResponse->assertSessionMissing('errors.email');

        // Only the known user actually receives mail.
        Notification::assertCount(1);
    }
}
