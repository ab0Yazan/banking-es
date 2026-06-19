<?php

namespace App\Domain\Account;

use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Shared\Events\DomainEvent;

final class AccountState
{
    public AccountId $id;

    public int $balance = 0;

    public bool $isFrozen = false;

    public function apply(DomainEvent $event): void
    {
        if ($event instanceof AccountOpened) {
            $this->id = $event->accountId;
        }

        if ($event instanceof MoneyDeposited) {
            $this->balance += $event->money->amount();
        }

        if ($event instanceof MoneyWithdrawn) {
            $this->balance -= $event->money->amount();
        }

        if ($event instanceof AccountFrozen) {
            $this->isFrozen = true;
        }
    }
}
