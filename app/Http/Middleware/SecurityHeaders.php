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

        // Security review M-1: Content-Security-Policy.
        //
        // Scope set to HTML responses only — JSON/PDF/CSV don't need it and
        // shipping a policy with them just bloats every response header.
        //
        // The policy currently keeps `'unsafe-inline'` on script/style because
        // Alpine.js relies on inline attributes (x-data, x-on:*) that count
        // as inline script expressions under CSP's script-src semantics.
        // That leaves us short of XSS-hardening nirvana but still lets us
        // bank the big wins:
        //   - object-src 'none'      — blocks legacy Flash/Java/plugin abuse
        //   - base-uri 'self'        — blocks <base href="…"> hijacking
        //   - form-action 'self'     — blocks form-posts to attacker origins
        //   - frame-ancestors 'self' — clickjacking mitigation at the CSP layer
        //   - upgrade-insecure-requests — stops mixed-content in production
        //
        // Next step (future): migrate inline handlers to Alpine listeners in
        // bundled JS, then drop `'unsafe-inline'` and switch script-src to
        // `'strict-dynamic'` with a per-request nonce.
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html') || $contentType === '') {
            $csp = [
                "default-src 'self'",
                // Scripts: self + inline (Alpine). Vite-built bundles are same-origin.
                "script-src 'self' 'unsafe-inline'",
                // Styles: self + inline (Tailwind JIT sometimes inlines).
                "style-src 'self' 'unsafe-inline'",
                // Images: self + data: (icons, SweetAlert2) + https: for Gravatar etc.
                "img-src 'self' data: https:",
                // Fonts: self + data: (embedded fonts).
                "font-src 'self' data:",
                // XHR / fetch / EventSource.
                "connect-src 'self'",
                // Media files (audio/video): none today.
                "media-src 'self'",
                // Plugins + base + forms + frames.
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ];

            if (app()->environment('production')) {
                $csp[] = 'upgrade-insecure-requests';
            }

            $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        // Cross-origin isolation. COOP blocks a malicious opener from
        // accessing window.opener references; CORP restricts which origins
        // can embed our responses.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        return $response;
    }
}
