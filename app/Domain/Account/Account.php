<?php

namespace App\Domain\Account;

use App\Domain\Account\Exceptions\InsufficientBalance;

final class Account
{
    private Money $balance;

    public function __construct(
        private readonly AccountId $id
    ) {
        $this->balance = new Money(0);
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function deposit(Money $money): void
    {
        $this->balance = $this->balance->add($money);
    }

    public function withdraw(Money $money): void
    {
        if ($this->balance->lessThan($money)) {
            throw new InsufficientBalance();
        }

        $this->balance = $this->balance->subtract($money);
    }
}