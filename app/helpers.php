<?php

use App\Support\TenantContext;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant
     *
     * @return \App\Models\Tenant|null
     */
    function tenant()
    {
        return app(TenantContext::class)->current();
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Get the current tenant ID
     *
     * @return int|null
     */
    function tenant_id()
    {
        return app(TenantContext::class)->id();
    }
}

if (! function_exists('tenant_api_token')) {
    /**
     * Get the current tenant API token.
     */
    function tenant_api_token()
    {
        return app(TenantContext::class)->apiToken();
    }
}

if (! function_exists('app_version')) {
    /**
     * Get the application version
     *
     * @return string
     */
    function app_version()
    {
        return config('app.version', '1.0.0');
    }
}

if (! function_exists('localized_date')) {
    /**
     * Format a date according to the current locale.
     *
     * Swiss convention: day before month (dd.MM.yyyy). We lean on Carbon's
     * isoFormat() with ICU patterns which automatically handle locale-specific
     * month names and date orderings.
     *
     *   localized_date(now())                  → "10 Apr 2026"   (en)
     *   localized_date(now())                  → "10. Apr. 2026" (de)
     *   localized_date(now(), 'long')          → "10 April 2026"
     *   localized_date(now(), 'datetime')      → "10 Apr 2026 14:30"
     */
    function localized_date(
        \Carbon\Carbon|\DateTimeInterface|string|null $date,
        string $format = 'short'
    ): string {
        if ($date === null) {
            return '';
        }

        $carbon = $date instanceof \Carbon\Carbon
            ? $date
            : \Carbon\Carbon::parse($date);

        $carbon->locale(app()->getLocale());

        return match ($format) {
            'long' => $carbon->isoFormat('D MMMM YYYY'),
            'datetime' => $carbon->isoFormat('D MMM YYYY HH:mm'),
            'time' => $carbon->isoFormat('HH:mm'),
            default => $carbon->isoFormat('D MMM YYYY'),
        };
    }
}

if (! function_exists('chf')) {
    /**
     * Format a numeric value as Swiss Francs.
     *
     * Uses the Swiss convention: apostrophe as thousands separator,
     * period as decimal separator, two decimal places.
     *
     *   chf(1234567.5)  → "CHF 1'234'567.50"
     *   chf(0.1 + 0.2)  → "CHF 0.30"
     *   chf(null)       → "CHF 0.00"
     *
     * Pass includeSymbol=false for just the number portion:
     *   chf(100, false) → "100.00"
     */
    function chf(int|float|string|null $amount, bool $includeSymbol = true): string
    {
        $value = (float) ($amount ?? 0);
        $formatted = number_format($value, 2, '.', "'");

        return $includeSymbol ? "CHF {$formatted}" : $formatted;
    }
}
