<?php

namespace ProPhoto\AI\Tests\Unit\DTOs;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AI\Money;

class MoneyTest extends TestCase
{
    public function test_construction_with_cents(): void
    {
        $money = new Money(150);

        $this->assertSame(150, $money->amount);
        $this->assertSame('USD', $money->currency);
    }

    public function test_construction_with_custom_currency(): void
    {
        $money = new Money(1000, 'EUR');

        $this->assertSame(1000, $money->amount);
        $this->assertSame('EUR', $money->currency);
    }

    public function test_to_dollars_converts_correctly(): void
    {
        $this->assertSame(1.5, (new Money(150))->toDollars());
        $this->assertSame(0.23, (new Money(23))->toDollars());
        $this->assertSame(0.0, (new Money(0))->toDollars());
        $this->assertSame(10.0, (new Money(1000))->toDollars());
    }

    public function test_add_combines_amounts(): void
    {
        $a = new Money(150);
        $b = new Money(23);
        $sum = $a->add($b);

        $this->assertSame(173, $sum->amount);
        $this->assertSame('USD', $sum->currency);
    }

    public function test_add_preserves_immutability(): void
    {
        $a = new Money(150);
        $b = new Money(23);
        $a->add($b);

        // Original unchanged (readonly class)
        $this->assertSame(150, $a->amount);
    }

    public function test_add_throws_for_different_currencies(): void
    {
        $usd = new Money(100, 'USD');
        $eur = new Money(100, 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add different currencies: USD + EUR');

        $usd->add($eur);
    }

    public function test_zero_creates_zero_amount(): void
    {
        $zero = Money::zero();

        $this->assertSame(0, $zero->amount);
        $this->assertSame('USD', $zero->currency);
    }

    public function test_zero_with_custom_currency(): void
    {
        $zero = Money::zero('EUR');

        $this->assertSame(0, $zero->amount);
        $this->assertSame('EUR', $zero->currency);
    }
}
