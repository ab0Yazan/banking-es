<?php

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Exceptions\AccountIsFrozen;
use App\Domain\Account\Exceptions\InsufficientBalance;
use App\Domain\Account\Money;
use App\Infrastructure\Bus\InMemoryEventBus;

it('opens a new account and records the event', function () {
    $id = AccountId::generate();
    $account = Account::open($id);

    expect($account->id()->equals($id))->toBeTrue();
    expect($account->balance()->amount())->toBe(0);

    $events = $account->releaseEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(AccountOpened::class);
});

it('deposits money', function () {
    $account = Account::open(AccountId::generate());

    $account->deposit(Money::fromInteger(500));

    expect($account->balance()->amount())->toBe(500);
});

it('cannot withdraw more than balance', function () {
    $account = Account::open(AccountId::generate());

    $account->withdraw(Money::fromInteger(100));

})->throws(InsufficientBalance::class);

it('publishes an event to subscribed listeners', function () {
    $bus = new InMemoryEventBus;
    $handled = false;

    $bus->subscribe(
        MoneyDeposited::class,
        function (MoneyDeposited $event) use (&$handled) {
            expect($event->money->amount())->toBe(500);
            $handled = true;
        }
    );

    $bus->publish(
        new MoneyDeposited(
            AccountId::generate(),
            Money::fromInteger(500)
        )
    );

    expect($handled)->toBeTrue();
});

it('records a deposit event', function () {
    $account = Account::open(AccountId::generate());

    $account->releaseEvents();

    $account->deposit(Money::fromInteger(500));

    $events = $account->releaseEvents();

    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(MoneyDeposited::class);
});

it('can freeze an account and records the event', function () {
    $account = Account::open(AccountId::generate());
    $account->releaseEvents();

    $account->freeze();

    expect($account->isFrozen())->toBeTrue();

    $events = $account->releaseEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(AccountFrozen::class);
});

it('cannot deposit money when account is frozen', function () {
    $account = Account::open(AccountId::generate());
    $account->freeze();

    $account->deposit(Money::fromInteger(100));

})->throws(AccountIsFrozen::class);

it('cannot withdraw money when account is frozen', function () {
    $account = Account::open(AccountId::generate());

    $account->deposit(Money::fromInteger(500));
    $account->freeze();

    $account->withdraw(Money::fromInteger(100));

})->throws(AccountIsFrozen::class);

it('does not record multiple freeze events if already frozen', function () {
    $account = Account::open(AccountId::generate());
    $account->releaseEvents();

    $account->freeze();
    $account->freeze();

    $events = $account->releaseEvents();
    expect($events)->toHaveCount(1);
});
