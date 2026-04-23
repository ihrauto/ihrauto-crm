<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add defensive HTTP response headers.
 *
 * WHY:
 *   - HSTS forces browsers to always use HTTPS, preventing downgrade attacks
 *     and stopping session hijacking on Wi-Fi networks.
 *   - X-Content-Type-Options prevents MIME-type sniffing attacks where a
 *     browser might treat an uploaded file as HTML/JS.
 *   - X-Frame-Options blocks framing by other sites, preventing clickjacking.
 *   - Referrer-Policy stops leaking authenticated URLs to external domains
 *     via the Referer header.
 *   - Permissions-Policy locks down powerful browser APIs we don't use.
 *
 * WHAT IS NOT HERE:
 *   - Content-Security-Policy (CSP) — tricky to get right and not currently
 *     needed because inline <script> blocks are unavoidable until we migrate
 *     JS to modules. Add this in a later sprint once views are cleaned up.
 *
 * HSTS SAFETY:
 *   - Only enabled in production to avoid breaking `php artisan serve` on
 *     localhost (which has no valid TLS cert).
 *   - `max-age=31536000` = 1 year (browser will refuse HTTP for this long).
 *   - `includeSubDomains` covers all subdomains under the apex.
 *   - `preload` is NOT set — preload requires submitting to the HSTS preload
 *     list manually and is hard to reverse. Add later if desired.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // HSTS: production only (localhost has no TLS cert)
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // MIME sniffing protection — prevents browser from guessing content type
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection — only allow same-origin framing
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Don't leak authenticated URLs to external sites
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Lock down powerful browser APIs we don't use
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=()'
        );

        return $response;
    }
}
