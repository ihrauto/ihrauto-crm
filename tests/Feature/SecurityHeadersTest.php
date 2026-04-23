<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint C.23 — SecurityHeaders middleware.
 */
class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function it_sets_x_content_type_options_header(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function it_sets_x_frame_options_header(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    #[Test]
    public function it_sets_referrer_policy_header(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function it_sets_permissions_policy_header(): void
    {
        $response = $this->get(route('login'));

        $header = $response->headers->get('Permissions-Policy');
        $this->assertNotNull($header);
        $this->assertStringContainsString('geolocation=()', $header);
        $this->assertStringContainsString('camera=()', $header);
        $this->assertStringContainsString('microphone=()', $header);
    }

    #[Test]
    public function it_does_not_set_hsts_in_testing_environment(): void
    {
        // HSTS is production-only — we don't want it breaking local HTTP dev.
        $response = $this->get(route('login'));

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    #[Test]
    public function security_headers_apply_to_authenticated_pages(): void
    {
        $response = $this->get(route('login'));

        // Spot-check all headers still present on a simple GET
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }
}
