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
    private AccountState $state;

    public function __construct()
    {
        $this->state = new AccountState();
    }

    public static function open(AccountId $id): self
    {
        $account = new self();
        $account->recordThat(new AccountOpened($id));

        return $account;
    }

    public function freeze(): void
    {
        if ($this->state->isFrozen) {
            throw new AccountIsFrozen;
        }

        $this->recordThat(new AccountFrozen($this->state->id));
    }

    public function deposit(Money $money): void
    {
        if ($this->state->isFrozen) {
            throw new AccountIsFrozen;
        }

        if ($money->amount() < 20) {
            throw new MinimumDepositRequired;
        }

        $this->recordThat(new MoneyDeposited($this->state->id, $money));
    }

    public function withdraw(Money $money): void
    {
        if ($this->state->isFrozen) {
            throw new AccountIsFrozen;
        }

        if ($this->state->balance < $money->amount()) {
            throw new InsufficientBalance;
        }

        $this->recordThat(new MoneyWithdrawn($this->state->id, $money));
    }

    /**
     * 🔴 هنا يتم تنفيذ الـ abstract method المطلوبة من الأب
     * عندما يقوم الأب باستدعاء $instance->apply($event) في كوده،
     * يتم التقاط الحدث هنا وتمريره فوراً لكائن الـ State المساعد.
     */
    protected function apply(DomainEvent $event): void
    {
        $this->state->apply($event);
    }

    public function id(): AccountId
    {
        return $this->state->id;
    }

    public function balance(): Money
    {
        return new Money($this->state->balance);
    }

    public function isFrozen(): bool
    {
        return $this->state->isFrozen;
    }
}