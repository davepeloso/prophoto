<?php

namespace ProPhoto\Contracts\DTOs;

readonly class AssetId
{
    public function __construct(
        public int|string $value
    ) {}

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public static function from(int|string $value): self
    {
        return new self($value);
    }
}
