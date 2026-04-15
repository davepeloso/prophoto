<?php
namespace ProPhoto\Contracts\DTOs\AI;

readonly class Money
{
    public function __construct(
        public int $amount,           // in cents
        public string $currency = 'USD',
    ) {}

    public function toDollars(): float
    {
        return $this->amount / 100;
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException("Cannot add different currencies: {$this->currency} + {$other->currency}");
        }
        return new self($this->amount + $other->amount, $this->currency);
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }
}
