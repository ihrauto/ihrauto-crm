<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChfFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_zero(): void
    {
        $this->assertSame('CHF 0.00', chf(0));
    }

    #[Test]
    public function it_handles_null_as_zero(): void
    {
        $this->assertSame('CHF 0.00', chf(null));
    }

    #[Test]
    public function it_formats_small_amounts(): void
    {
        $this->assertSame('CHF 12.50', chf(12.5));
    }

    #[Test]
    public function it_uses_apostrophe_for_thousands(): void
    {
        $this->assertSame("CHF 1'234.56", chf(1234.56));
    }

    #[Test]
    public function it_handles_millions(): void
    {
        $this->assertSame("CHF 1'234'567.89", chf(1234567.89));
    }

    #[Test]
    public function it_rounds_to_two_decimals(): void
    {
        $this->assertSame('CHF 0.30', chf(0.1 + 0.2));
    }

    #[Test]
    public function it_handles_negative_values(): void
    {
        $this->assertSame('CHF -500.00', chf(-500));
    }

    #[Test]
    public function it_can_omit_the_symbol(): void
    {
        $this->assertSame("1'234.56", chf(1234.56, includeSymbol: false));
    }

    #[Test]
    public function it_accepts_string_numeric_input(): void
    {
        $this->assertSame('CHF 42.00', chf('42'));
    }
}
