<?php

namespace App\Domain\Account;

use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\CardIssued;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Events\MoneyWithdrawnViaCard;
use App\Domain\Account\Exceptions\AccountIsFrozen;
use App\Domain\Account\Exceptions\CardNotFound;
use App\Domain\Account\Exceptions\InsufficientBalance;
use App\Domain\Account\Exceptions\MinimumDepositRequired;
use App\Domain\Shared\AggregateRoot;
use App\Domain\Shared\Events\DomainEvent;

final class Account extends AggregateRoot
{
    private AccountState $state;

    public function __construct()
    {
        $this->state = new AccountState;
    }

    public static function open(AccountId $id): self
    {
        $account = new self;
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

    public function issueCard(string $cardNumber, int $dailyLimit): void
    {
        $this->recordThat(new CardIssued($this->state->id, $cardNumber, $dailyLimit));
    }

    public function withdrawViaCard(string $cardNumber, Money $money): void
    {
        if (! isset($this->state->cards[$cardNumber])) {
            throw new CardNotFound('البطاقة المستخدمة غير مسجلة في هذا الحساب!');
        }

        $card = $this->state->cards[$cardNumber];

        $card->verifyCanWithdraw($money->amount());

        if ($this->state->balance < $money->amount()) {
            throw new InsufficientBalance;
        }

        $this->recordThat(new MoneyWithdrawnViaCard($this->state->id, $cardNumber, $money));
    }
}
