<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint B.11 — tighter password reset rate limits.
 *
 * Previously the rate limit was 5 requests per minute. This test verifies the
 * new 3 per 5 minutes limit and ensures legitimate traffic still succeeds.
 */
class PasswordResetRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        RateLimiter::clear('forgot-password');
        RateLimiter::clear('reset-password');
    }

    #[Test]
    public function forgot_password_allows_three_requests_then_throttles(): void
    {
        // 3 allowed
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->post(route('password.email'), ['email' => "user{$i}@example.com"]);
            $this->assertNotSame(429, $response->status(), "Request {$i} should not be throttled");
        }

        // 4th should be throttled
        $response = $this->post(route('password.email'), ['email' => 'user4@example.com']);
        $this->assertSame(429, $response->status(), '4th request within 5 minutes must be throttled');
    }

    #[Test]
    public function reset_password_allows_three_requests_then_throttles(): void
    {
        // 3 allowed (even with invalid token, the rate limiter still counts)
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->post(route('password.store'), [
                'token' => 'fake-token',
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
            $this->assertNotSame(429, $response->status(), "Request {$i} should not be throttled");
        }

        // 4th should be throttled
        $response = $this->post(route('password.store'), [
            'token' => 'fake-token',
            'email' => 'user4@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $this->assertSame(429, $response->status());
    }
}
