<?php

namespace App\Domain\Account;

use Ramsey\Uuid\Uuid;

final readonly class AccountId
{
    public function __construct(
        public string $value
    ) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid7()->toString());
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function equals(AccountId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}