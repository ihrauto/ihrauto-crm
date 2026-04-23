<?php

namespace Tests\Unit\Support;

use App\Support\LicensePlate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LicensePlateTest extends TestCase
{
    #[Test]
    public function it_uppercases_letters(): void
    {
        $this->assertSame('ZH123456', LicensePlate::normalize('zh123456'));
    }

    #[Test]
    public function it_removes_internal_spaces(): void
    {
        $this->assertSame('ZH123456', LicensePlate::normalize('ZH 123 456'));
    }

    #[Test]
    public function it_trims_leading_and_trailing_whitespace(): void
    {
        $this->assertSame('ZH123456', LicensePlate::normalize('  ZH123456  '));
    }

    #[Test]
    public function it_handles_mixed_spacing_and_casing(): void
    {
        $this->assertSame('ZH123456', LicensePlate::normalize('  zh 1 2 3 4 5 6 '));
    }

    #[Test]
    public function it_returns_empty_string_for_null(): void
    {
        $this->assertSame('', LicensePlate::normalize(null));
    }

    #[Test]
    public function it_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', LicensePlate::normalize(''));
    }

    #[Test]
    public function it_strips_non_ascii_characters(): void
    {
        // S-15 changed this rule: any non-[A-Z0-9] character is stripped
        // so Cyrillic / zero-width lookalikes can't produce duplicate
        // vehicle rows for the same physical plate. Swiss & EU plates
        // only use ASCII alphanumeric in practice.
        $this->assertSame('B123', LicensePlate::normalize('bö 123'));
    }

    #[Test]
    public function it_strips_zero_width_and_nbsp_characters(): void
    {
        $input = "ZH\u{200B}123\u{00A0}456";
        $this->assertSame('ZH123456', LicensePlate::normalize($input));
    }

    #[Test]
    public function it_strips_cyrillic_homoglyphs(): void
    {
        // Cyrillic "ВЕ" (U+0412 U+0415) looks like Latin "BE" but is a
        // different byte sequence — must be stripped, not kept.
        $this->assertSame('123', LicensePlate::normalize("\u{0412}\u{0415}123"));
    }

    #[Test]
    public function where_expression_returns_exact_match_by_default(): void
    {
        [$expr, $bindings] = LicensePlate::whereExpression('ZH 123 456');

        $this->assertStringContainsString('UPPER(REPLACE(license_plate', $expr);
        $this->assertStringContainsString('=', $expr);
        $this->assertEquals(['ZH123456'], $bindings);
    }

    #[Test]
    public function where_expression_supports_like_matching(): void
    {
        [$expr, $bindings] = LicensePlate::whereExpression('zh', like: true);

        $this->assertStringContainsString('LIKE', $expr);
        $this->assertEquals(['%ZH%'], $bindings);
    }
}
