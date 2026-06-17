<?php

namespace App\Domain\Account;

use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Exceptions\InsufficientBalance;
use App\Domain\Shared\AggregateRoot;
use App\Domain\Shared\Events\DomainEvent;

final class Account extends AggregateRoot
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
        $this->recordThat(
            new MoneyDeposited(
                $this->id,
                $money
            )
        );
    }

    public function withdraw(Money $money): void
    {
        if ($this->balance->lessThan($money)) {
            throw new InsufficientBalance;
        }

        $this->recordThat(
            new MoneyWithdrawn(
                $this->id,
                $money
            )
        );
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {

            $event instanceof MoneyDeposited => $this->balance = $this->balance->add($event->money),

            $event instanceof MoneyWithdrawn => $this->balance = $this->balance->subtract($event->money),

            default => null,
        };
    }
}
