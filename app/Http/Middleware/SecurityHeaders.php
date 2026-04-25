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
        // Generate a per-request CSP nonce. Bound to the container before
        // the response is built so views can read it via the `csp_nonce()`
        // helper. Used by inline <script>/<style> blocks that opt in.
        // Once enough of the codebase has migrated, we can drop
        // `'unsafe-inline'` entirely and rely on `'strict-dynamic'` + nonce.
        if (! app()->bound('csp.nonce')) {
            app()->instance('csp.nonce', base64_encode(random_bytes(18)));
        }
        $nonce = app('csp.nonce');

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
            // Google Fonts: the layout loads Inter from fonts.googleapis.com
            // (the stylesheet) which then pulls font files from
            // fonts.gstatic.com. Both origins must be allowlisted.
            // In local dev the Vite HMR server lives at http://localhost:5173
            // (force IPv4 binding via vite.config.js — the bracketed IPv6
            // form `http://[::1]:5173` is rejected by browsers as an
            // invalid CSP source expression). Allowlisted only when
            // APP_ENV=local.
            $viteSources = app()->environment('local')
                ? ' http://localhost:5173 ws://localhost:5173'
                : '';
            // Alpine.js v3 compiles its `x-*` expression strings via
            // `new Function(...)`, which CSP considers `unsafe-eval`.
            // Only relax this in local/dev — production builds should
            // migrate to @alpinejs/csp to avoid needing unsafe-eval at
            // all (tracked as ENG-008 in the engineering board).
            $unsafeEval = app()->environment('local') ? " 'unsafe-eval'" : '';
            $csp = [
                "default-src 'self'",
                // Scripts: self + inline (Alpine). Vite-built bundles are
                // same-origin. The per-request nonce is generated and
                // exposed via csp_nonce() so views can opt in, but it is
                // intentionally NOT added to the directive yet — per CSP
                // Level 3, adding a nonce DISABLES 'unsafe-inline', which
                // would block every unmigrated inline <script>/<style>
                // (including the layout's own .main-content margin rule,
                // breaking the page layout). The nonce only enters the
                // directive once every inline block in the codebase
                // carries it. See ENG-008 step-by-step roadmap.
                "script-src 'self' 'unsafe-inline'".$unsafeEval.$viteSources,
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$viteSources,
                // Images: self + data: (icons, SweetAlert2) + https: for Gravatar etc.
                "img-src 'self' data: https:",
                // Fonts: self + data: (embedded fonts) + Google Fonts asset origin.
                "font-src 'self' data: https://fonts.gstatic.com",
                // XHR / fetch / EventSource + Vite HMR in local dev.
                "connect-src 'self'".$viteSources,
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
