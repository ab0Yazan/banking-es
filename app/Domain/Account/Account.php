<?php

namespace App\Domain\Account;

use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Exceptions\AccountIsFrozen;
use App\Domain\Account\Exceptions\InsufficientBalance;
use App\Domain\Account\Exceptions\MinimumDepositRequired;
use App\Domain\Shared\AggregateRoot;
use App\Domain\Shared\Events\DomainEvent;

final class Account extends AggregateRoot
{
    private Money $balance;

    private AccountId $id;

    private bool $isFrozen = false;

    protected function __construct()
    {
        $this->balance = Money::fromInteger(0);
    }

    public static function open(AccountId $id): self
    {
        $account = new self;
        $account->recordThat(new AccountOpened($id));

        return $account;
    }

    public function freeze(): void
    {
        if ($this->isFrozen) {
            return;
        }

        $this->recordThat(new AccountFrozen($this->id));
    }

    public function deposit(Money $money): void
    {
        if ($this->isFrozen) {
            throw new AccountIsFrozen;
        }

        if ($money->amount() < 20) {
            throw new MinimumDepositRequired;
        }

        $this->recordThat(new MoneyDeposited($this->id, $money));
    }

    public function withdraw(Money $money): void
    {
        if ($this->isFrozen) {
            throw new AccountIsFrozen;
        }

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
            $event instanceof AccountOpened => $this->hydrateAccount($event),
            $event instanceof MoneyDeposited => $this->balance = $this->balance->add($event->money),
            $event instanceof MoneyWithdrawn => $this->balance = $this->balance->subtract($event->money),
            $event instanceof AccountFrozen => $this->isFrozen = true,
            default => null,
        };
    }

    private function hydrateAccount(AccountOpened $event): void
    {
        $this->id = $event->accountId;
        $this->balance = new Money(0);
        $this->isFrozen = false;
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function isFrozen(): bool
    {
        return $this->isFrozen;
    }
}
