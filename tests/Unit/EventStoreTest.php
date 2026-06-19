<?php

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Money;
use App\Infrastructure\Bus\InMemoryEventBus;
use App\Infrastructure\EventStore\EventStoreRepository;
use App\Infrastructure\Projection\AccountBalanceProjector;
use App\Infrastructure\Projection\ProjectionReplayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('executes a complete banking business scenario successfully with version handling', function () {
    $bus = new InMemoryEventBus;
    $repository = new EventStoreRepository;
    $projector = new AccountBalanceProjector;

    // ربط الـ Projector بجميع الأحداث مع تمرير الـ version ديناميكياً في السلسلة
    $versionTracker = 1;
    $bus->subscribe(AccountOpened::class, fn ($e) => $projector->handleAccountOpened($e, 1));
    $bus->subscribe(MoneyDeposited::class, fn ($e) => $projector->handleMoneyDeposited($e, ++$versionTracker));
    
    // لإعادة التصفير الآمن في المحاكاة الحية للتيست
    $versionTracker = 2; 
    $bus->subscribe(MoneyWithdrawn::class, fn ($e) => $projector->handleMoneyWithdrawn($e, 4));
    $bus->subscribe(AccountFrozen::class, fn ($e) => $projector->handleAccountFrozen($e, 5));

    $accountId = AccountId::generate();

    $account = Account::open($accountId);
    $account->deposit(Money::fromInteger(1000));
    $account->deposit(Money::fromInteger(500));
    $account->withdraw(Money::fromInteger(300));
    $account->freeze();

    $eventsToPublish = $account->releaseEvents();
    expect($eventsToPublish)->toHaveCount(5);

    $version = 1;
    foreach ($eventsToPublish as $event) {
        $eventData = match (get_class($event)) {
            AccountOpened::class => ['accountId' => $accountId->toString()],
            AccountFrozen::class => ['accountId' => $accountId->toString()],
            MoneyDeposited::class, MoneyWithdrawn::class => [
                'accountId' => $accountId->toString(),
                'money' => ['amount' => $event->money->amount()],
            ],
        };

        DB::table('stored_events')->insert([
            'aggregate_id' => (string) $accountId,
            'event_type' => get_class($event),
            'event_data' => json_encode($eventData),
            'version' => $version,
        ]);

        match (get_class($event)) {
            AccountOpened::class  => $projector->handleAccountOpened($event, $version),
            MoneyDeposited::class => $projector->handleMoneyDeposited($event, $version),
            MoneyWithdrawn::class => $projector->handleMoneyWithdrawn($event, $version),
            AccountFrozen::class  => $projector->handleAccountFrozen($event, $version),
        };
        $version++;
    }

    dump(DB::table('account_balances')->get()->toArray());

    $this->assertDatabaseHas('account_balances', [
        'account_id' => (string) $accountId,
        'balance' => 1200, 
        'total_deposited_ever' => 1500,
        'is_frozen' => true,
        'last_version' => 5
    ]);

    $reconstitutedAccount = $repository->getById($accountId);
    expect($reconstitutedAccount->balance()->amount())->toBe(1200);
    expect($reconstitutedAccount->getVersion())->toBe(5);
});


it('ensures projector is idempotent and protects against duplicate events', function () {
    $projector = new AccountBalanceProjector();
    $accountId = AccountId::generate();

    $projector->handleAccountOpened(new AccountOpened($accountId), 1);
    
    $depositEvent = new MoneyDeposited($accountId, Money::fromInteger(400));
    $projector->handleMoneyDeposited($depositEvent, 2);

    $this->assertDatabaseHas('account_balances', [
        'account_id' => $accountId->toString(),
        'balance' => 400,
        'last_version' => 2
    ]);

    $projector->handleMoneyDeposited($depositEvent, 2);

    $this->assertDatabaseHas('account_balances', [
        'account_id' => $accountId->toString(),
        'balance' => 400,
        'last_version' => 2
    ]);
});

it('can successfully replay all historical events from scratch to build new projection columns', function () {
    $projector = new AccountBalanceProjector();
    $replayer = new ProjectionReplayer($projector);
    $accountId = AccountId::generate();

    $historicalEvents = [
        ['type' => AccountOpened::class, 'version' => 1, 'data' => ['accountId' => $accountId->toString()]],
        ['type' => MoneyDeposited::class, 'version' => 2, 'data' => ['accountId' => $accountId->toString(), 'money' => ['amount' => 700]]],
        ['type' => MoneyWithdrawn::class, 'version' => 3, 'data' => ['accountId' => $accountId->toString(), 'money' => ['amount' => 200]]],
    ];

    foreach ($historicalEvents as $evt) {
        DB::table('stored_events')->insert([
            'aggregate_id' => $accountId->toString(),
            'event_type' => $evt['type'],
            'event_data' => json_encode($evt['data']),
            'version' => $evt['version'],
        ]);
    }

    DB::table('account_balances')->truncate();
    $this->assertDatabaseCount('account_balances', 0);

    $replayer->replayAll();

    $this->assertDatabaseHas('account_balances', [
        'account_id' => $accountId->toString(),
        'balance' => 500, 
        'total_deposited_ever' => 700, 
        'last_version' => 3
    ]);
});