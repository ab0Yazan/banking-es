<?php

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Exceptions\InsufficientBalance;
use App\Domain\Account\Money;
use App\Infrastructure\Bus\InMemoryEventBus;

it('deposits money', function () {

    $account = new Account(
        AccountId::generate()
    );

    $account->deposit(
        new Money(500)
    );

    expect(
        $account->balance()->amount()
    )->toBe(500);

});

it('cannot withdraw more than balance', function () {

    $account = new Account(
        AccountId::generate()
    );

    $account->withdraw(
        new Money(100)
    );

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
            new Money(500)
        )
    );

    expect($handled)->toBeTrue();
});

it('records a deposit event', function () {

    $account = new Account(
        AccountId::generate()
    );

    $account->deposit(
        new Money(500)
    );

    $events = $account->releaseEvents();

    expect($events)->toHaveCount(1);

    expect($events[0])
        ->toBeInstanceOf(MoneyDeposited::class);

});

it('applies deposit event', function () {

    $account = new Account(
        AccountId::generate()
    );

    $account->deposit(
        new Money(500)
    );

    expect(
        $account->balance()->amount()
    )->toBe(500);

});
