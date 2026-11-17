<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_arithmetic_is_exact(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('1050.00', Money::add(1000, '50'));
        $this->assertSame('-5.50', Money::sub('10', '15.50'));
        $this->assertSame('52.50', Money::percent('1050', '5'));
        $this->assertSame('0.01', Money::percent('0.10', '5'));     // 0.005 rounds half up
        $this->assertSame('146.25', Money::mul('11.25', '13'));
    }

    public function test_indian_format(): void
    {
        $this->assertSame('₹12,34,567.00', Money::format(1234567));
        $this->assertSame('₹799.00', Money::format('799'));
        $this->assertSame('-₹1,000.50', Money::format(-1000.5));
        $this->assertSame('₹0.00', Money::format(null));
    }

    public function test_phone_normalisation(): void
    {
        $this->assertSame('9876543210', Phone::normalize('+91 98765-43210'));
        $this->assertSame('9876543210', Phone::normalize('09876543210'));
        $this->assertTrue(Phone::isValid('9876543210'));
        $this->assertFalse(Phone::isValid('1234567890'));
        $this->assertNull(Phone::normalize(null));
    }
}
