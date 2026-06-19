<?php

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Exceptions\InsufficientBalance;
use App\Domain\Account\Exceptions\MinimumDepositRequired;
use App\Domain\Account\Money;
use App\Infrastructure\Bus\InMemoryEventBus;
use App\Infrastructure\EventStore\EventStoreRepository;
use App\Infrastructure\Projection\AccountBalanceProjector;
use App\Infrastructure\Projection\ProjectionReplayer;
use App\Infrastructure\Queries\AccountDepositReportQuery;
use App\Infrastructure\Reactors\AccountNotificationReactor;
use App\Infrastructure\Services\NotificationServiceInterface;
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
            AccountOpened::class => $projector->handleAccountOpened($event, $version),
            MoneyDeposited::class => $projector->handleMoneyDeposited($event, $version),
            MoneyWithdrawn::class => $projector->handleMoneyWithdrawn($event, $version),
            AccountFrozen::class => $projector->handleAccountFrozen($event, $version),
        };
        $version++;
    }

    dump(DB::table('account_balances')->get()->toArray());

    $this->assertDatabaseHas('account_balances', [
        'account_id' => (string) $accountId,
        'balance' => 1200,
        'total_deposited_ever' => 1500,
        'is_frozen' => true,
        'last_version' => 5,
    ]);

    $reconstitutedAccount = $repository->getById($accountId);
    expect($reconstitutedAccount->balance()->amount())->toBe(1200);
    expect($reconstitutedAccount->getVersion())->toBe(5);
});

it('ensures projector is idempotent and protects against duplicate events', function () {
    $projector = new AccountBalanceProjector;
    $accountId = AccountId::generate();

    $projector->handleAccountOpened(new AccountOpened($accountId), 1);

    $depositEvent = new MoneyDeposited($accountId, Money::fromInteger(400));
    $projector->handleMoneyDeposited($depositEvent, 2);

    $this->assertDatabaseHas('account_balances', [
        'account_id' => $accountId->toString(),
        'balance' => 400,
        'last_version' => 2,
    ]);

    $projector->handleMoneyDeposited($depositEvent, 2);

    $this->assertDatabaseHas('account_balances', [
        'account_id' => $accountId->toString(),
        'balance' => 400,
        'last_version' => 2,
    ]);
});

it('can successfully replay all historical events from scratch to build new projection columns', function () {
    $projector = new AccountBalanceProjector;
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
        'last_version' => 3,
    ]);
});

it('uses relational database capabilities and updates timestamps during projection', function () {
    $projector = new AccountBalanceProjector;
    $accountId = AccountId::generate();

    $projector->handleAccountOpened(
        new AccountOpened($accountId),
        1
    );

    $projector->handleMoneyDeposited(
        new MoneyDeposited($accountId, Money::fromInteger(1000)),
        2
    );

    $rowBefore = DB::table('account_balances')->where('account_id', $accountId->toString())->first();
    expect($rowBefore->updated_at)->not->toBeNull();

    $this->travel(1)->seconds();

    $projector->handleMoneyWithdrawn(
        new MoneyWithdrawn($accountId, Money::fromInteger(400)),
        3
    );

    $rowAfter = DB::table('account_balances')->where('account_id', $accountId->toString())->first();

    expect($rowAfter->balance)->toBe(600);
    expect($rowAfter->last_version)->toBe(3);
    expect($rowAfter->updated_at)->not->toBe($rowBefore->updated_at);
});

it('calculates total deposits accurately for a dynamic date range via event query', function () {
    $query = new AccountDepositReportQuery;

    $accountId = AccountId::generate();
    $otherAccountId = AccountId::generate();

    $targetFrom = new DateTimeImmutable('2026-06-10 00:00:00');
    $targetTo = new DateTimeImmutable('2026-06-15 23:59:59');

    $inRangeEvents = [
        ['amount' => 1000, 'date' => '2026-06-11 10:00:00'],
        ['amount' => 500,  'date' => '2026-06-14 15:30:00'],
    ];

    $version = 1;
    foreach ($inRangeEvents as $evt) {
        DB::table('stored_events')->insert([
            'aggregate_id' => $accountId->toString(),
            'event_type' => MoneyDeposited::class,
            'event_data' => json_encode(['accountId' => $accountId->toString(), 'money' => ['amount' => $evt['amount']]]),
            'version' => $version++,
            'created_at' => $evt['date'],
        ]);
    }

    $outOfRangeEvents = [
        ['amount' => rand(100, 900), 'date' => '2026-06-05 09:00:00'],
        ['amount' => rand(100, 900), 'date' => '2026-06-20 18:00:00'],
    ];

    foreach ($outOfRangeEvents as $evt) {
        DB::table('stored_events')->insert([
            'aggregate_id' => $accountId->toString(),
            'event_type' => MoneyDeposited::class,
            'event_data' => json_encode(['accountId' => $accountId->toString(), 'money' => ['amount' => $evt['amount']]]),
            'version' => $version++,
            'created_at' => $evt['date'],
        ]);
    }

    DB::table('stored_events')->insert([
        'aggregate_id' => $otherAccountId->toString(),
        'event_type' => MoneyDeposited::class,
        'event_data' => json_encode(['accountId' => $otherAccountId->toString(), 'money' => ['amount' => 9999]]),
        'version' => 1,
        'created_at' => '2026-06-12 12:00:00',
    ]);

    $totalResult = $query->execute($accountId, $targetFrom, $targetTo);

    expect($totalResult)->toBe(1500);
});

it('ignores other event types like withdrawals when calculating deposit reports', function () {
    $query = new AccountDepositReportQuery;
    $accountId = AccountId::generate();

    $from = new DateTimeImmutable('2026-06-01 00:00:00');
    $to = new DateTimeImmutable('2026-06-30 23:59:59');

    DB::table('stored_events')->insert([
        'aggregate_id' => $accountId->toString(),
        'event_type' => MoneyDeposited::class,
        'event_data' => json_encode(['accountId' => $accountId->toString(), 'money' => ['amount' => 700]]),
        'version' => 1,
        'created_at' => '2026-06-15 12:00:00',
    ]);

    DB::table('stored_events')->insert([
        'aggregate_id' => $accountId->toString(),
        'event_type' => MoneyWithdrawn::class,
        'event_data' => json_encode(['accountId' => $accountId->toString(), 'money' => ['amount' => 300]]),
        'version' => 2,
        'created_at' => '2026-06-16 12:00:00',
    ]);

    $totalResult = $query->execute($accountId, $from, $to);

    expect($totalResult)->toBe(700);
});

it('triggers an SMS notification side-effect when money is withdrawn', function () {
    $notificationServiceMock = Mockery::mock(NotificationServiceInterface::class);

    $accountId = AccountId::generate();
    $expectedMessage = 'تنبيه بنكي: تم سحب مبلغ 300 من حسابك بنجاح.';

    $notificationServiceMock->shouldReceive('sendSms')
        ->once()
        ->with($accountId->toString(), $expectedMessage)
        ->andReturn(true);

    $reactor = new AccountNotificationReactor($notificationServiceMock);

    $event = new MoneyWithdrawn($accountId, Money::fromInteger(300));
    $reactor->handleMoneyWithdrawn($event);

    expect(true)->toBeTrue();
});

it('enforces minimum deposit business rule strictly', function () {
    $accountId = AccountId::generate();
    $account = Account::open($accountId);

    expect(fn () => $account->deposit(Money::fromInteger(15)))
        ->toThrow(MinimumDepositRequired::class);
});

it('prevents withdrawal if aggregate state balance is insufficient', function () {
    $accountId = AccountId::generate();
    $account = Account::open($accountId);

    $account->deposit(Money::fromInteger(50));
    $account->releaseEvents();

    expect(fn () => $account->withdraw(Money::fromInteger(60)))
        ->toThrow(InsufficientBalance::class);
});
