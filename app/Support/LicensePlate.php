<?php

namespace App\Support;

/**
 * License plate normalization and lookup helpers.
 *
 * Swiss plates have variable formatting ("ZH 123 456", "ZH 123456", "zh123456")
 * and customers often submit them inconsistently. To prevent duplicate vehicle
 * records for the same physical plate, every lookup and write goes through
 * {@see normalize()}.
 *
 * NORMALIZATION RULES:
 *   - Trim whitespace at both ends
 *   - Remove ALL internal whitespace
 *   - Uppercase ASCII letters
 *   - Preserve non-ASCII characters as-is (future EU plates may use them)
 *
 * Example: "  zh 123 456 "  →  "ZH123456"
 */
class LicensePlate
{
    /**
     * Normalize a raw license plate string for storage and comparison.
     */
    public static function normalize(?string $plate): string
    {
        if ($plate === null) {
            return '';
        }

        // Remove all whitespace (space, tab, newline, zero-width) and uppercase.
        $stripped = preg_replace('/\s+/u', '', trim($plate));

        return mb_strtoupper($stripped ?? '', 'UTF-8');
    }

    /**
     * Return the WHERE-compatible SQL expression + bindings for matching a
     * normalized plate against the database column. The column value is
     * normalized server-side (UPPER + whitespace removal) for fuzzy lookups.
     *
     * Usage:
     *   [$expr, $bindings] = LicensePlate::whereExpression($plate);
     *   Vehicle::whereRaw($expr, $bindings)->first();
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function whereExpression(string $plate, bool $like = false): array
    {
        $normalized = self::normalize($plate);
        $value = $like ? "%{$normalized}%" : $normalized;

        return [
            "UPPER(REPLACE(license_plate, ' ', '')) ".($like ? 'LIKE' : '=').' ?',
            [$value],
        ];
    }
}
