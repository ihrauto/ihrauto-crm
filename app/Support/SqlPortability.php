<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cross-driver SQL fragment helpers.
 *
 * Bug review LOG-02: portions of the codebase used PostgreSQL-only SQL
 * fragments (`TO_CHAR`, `FILTER (WHERE ...)`) inside `selectRaw` / `groupByRaw`
 * / `orderByRaw`. That's fine in production — we run on PostgreSQL — but
 * the CI test suite runs against SQLite for speed, and CI would NOT catch
 * regressions in code paths that only exercise these fragments under
 * integration tests.
 *
 * This helper centralises the portability mapping so every query that
 * needs the same fragment gets the same implementation for each driver.
 * If we later add MySQL support (unlikely but possible), we just add a
 * `mysql` arm here instead of grepping for raw strings across the app.
 */
class SqlPortability
{
    /**
     * Driver-specific expression that formats a date/datetime column as
     * `YYYY-MM` (e.g. "2026-04"). Use inside selectRaw / groupByRaw /
     * orderByRaw. The returned string is ready to be interpolated into
     * raw SQL — do NOT pass user-controlled column names.
     *
     *   Example:
     *     ->selectRaw(SqlPortability::yearMonth('payment_date').' as month')
     *     ->groupByRaw(SqlPortability::yearMonth('payment_date'))
     *
     * @param  string  $column  column name, must be a trusted identifier (no user input)
     */
    public static function yearMonth(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "TO_CHAR($column, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', $column)",
            'mysql', 'mariadb' => "DATE_FORMAT($column, '%Y-%m')",
            'sqlsrv' => "FORMAT($column, 'yyyy-MM')",
            // Default to PG syntax — any unknown driver is most likely
            // a PG-compatible fork (Aurora, CockroachDB).
            default => "TO_CHAR($column, 'YYYY-MM')",
        };
    }

    /**
     * Short driver name for the default connection. Cached at request
     * level — the driver cannot change mid-request.
     */
    private static function driver(): string
    {
        /** @var string|null $cached */
        static $cached = null;
        $cached ??= DB::connection()->getDriverName();

        return $cached;
    }
}
