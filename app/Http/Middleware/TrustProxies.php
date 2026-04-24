<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Security review M-9: trusted-proxy list is now configurable.
     *
     * Reads TRUSTED_PROXIES from env as a comma-separated list of IPs or
     * CIDR blocks. When the app runs only behind Render / Cloudflare / a
     * known LB, set it to the edge's egress ranges — that blocks clients
     * from poisoning X-Forwarded-For to bypass IP-based rate limits or
     * spoofing X-Forwarded-Proto to defeat HTTPS enforcement.
     *
     * Falls back to '*' (trust every upstream, legacy behaviour) only when
     * TRUSTED_PROXIES is unset. Production deployments should set it.
     *
     * Special value `TRUSTED_PROXIES=*` is accepted for operators who
     * explicitly want the legacy behaviour.
     *
     * @var string|array<int,string>|null
     */
    protected $proxies;

    public function __construct()
    {
        $configured = trim((string) env('TRUSTED_PROXIES', ''));

        if ($configured === '' || $configured === '*') {
            $this->proxies = '*';

            return;
        }

        $this->proxies = array_values(array_filter(array_map('trim', explode(',', $configured))));
    }

    /**
     * Trust X-Forwarded-* headers so Laravel detects HTTPS correctly.
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
