<?php

namespace ProPhoto\Contracts\DTOs;

readonly class PermissionDecision
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null
    ) {}

    public static function allow(?string $reason = null): self
    {
        return new self(true, $reason);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isDenied(): bool
    {
        return !$this->allowed;
    }
}
