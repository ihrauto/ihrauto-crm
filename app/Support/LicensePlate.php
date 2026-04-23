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
 *   - Strip every character that isn't an ASCII letter or digit
 *     (kills zero-width spaces, Cyrillic look-alikes, emoji, dashes)
 *   - Uppercase
 *
 * S-15: previously we only stripped whitespace, which left non-ASCII
 * homoglyphs (e.g. Cyrillic "А" vs Latin "A") in place — two different
 * byte sequences that look identical in the UI become two different
 * vehicle rows for the same physical plate. Swiss and EU plates only
 * use [A-Z0-9], so an ASCII allowlist is both safer and sufficient.
 *
 * Example: "  zh 123-456  "  →  "ZH123456"
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

        // S-15: ASCII allowlist. Anything that isn't [A-Z0-9] (case-
        // insensitive) is dropped — whitespace, punctuation, diacritics,
        // and any unicode look-alike.
        $stripped = preg_replace('/[^A-Za-z0-9]/u', '', $plate) ?? '';

        return strtoupper($stripped);
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
