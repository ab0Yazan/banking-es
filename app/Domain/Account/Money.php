<?php

namespace App\Domain\Account;

final readonly class Money
{
    public function __construct(
        private int $amount
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException(
                'Money cannot be negative.'
            );
        }
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function add(Money $money): Money
    {
        return new Money(
            $this->amount + $money->amount
        );
    }

    public function subtract(Money $money): Money
    {
        return new Money(
            $this->amount - $money->amount
        );
    }

    public function greaterThan(Money $money): bool
    {
        return $this->amount > $money->amount;
    }

    public function lessThan(Money $money): bool
    {
        return $this->amount < $money->amount;
    }

    public function equals(Money $money): bool
    {
        return $this->amount === $money->amount;
    }

    public static function fromInteger(int $amount): Money
    {
        return new Money($amount);
    }
}
