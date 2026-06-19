<?php

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Exceptions\AccountIsFrozen;
use App\Domain\Account\Money;
use App\Infrastructure\Bus\InMemoryEventBus;
use App\Infrastructure\EventStore\EventStoreRepository;
use App\Infrastructure\Projection\AccountBalanceProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('integrates event store and projection seamlessly during a deposit', function () {
    $bus = new InMemoryEventBus;
    $repository = new EventStoreRepository;
    $projector = new AccountBalanceProjector;

    $bus->subscribe(
        MoneyDeposited::class,
        fn (MoneyDeposited $event) => $projector->handleMoneyDeposited($event)
    );

    $accountId = AccountId::generate();

    DB::table('account_balances')->insert([
        'account_id' => (string) $accountId,
        'balance' => 0,
        'is_frozen' => false,
    ]);

    $account = Account::open($accountId);
    $account->releaseEvents();

    $account->deposit(Money::fromInteger(500));

    $eventsToPublish = $account->releaseEvents();

    foreach ($eventsToPublish as $event) {
        DB::table('stored_events')->insert([
            'aggregate_id' => (string) $accountId,
            'event_type' => get_class($event),
            'event_data' => json_encode(['accountId' => (string) $accountId, 'money' => ['amount' => 500]]),
            'version' => 1,
        ]);

        $bus->publish($event);
    }

    $this->assertDatabaseHas('stored_events', [
        'aggregate_id' => (string) $accountId,
        'event_type' => MoneyDeposited::class,
        'version' => 1,
    ]);

    $this->assertDatabaseHas('account_balances', [
        'account_id' => (string) $accountId,
        'balance' => 500,
    ]);

    $reconstitutedAccount = $repository->getById($accountId);

    expect($reconstitutedAccount->balance()->amount())->toBe(500);
    expect($reconstitutedAccount->getVersion())->toBe(1);
});

it('executes a complete banking business scenario successfully', function () {
    // ==========================================
     // 1. التجهيز (Setup البنية التحتية)
    // ==========================================
    $bus = new InMemoryEventBus;
    $repository = new EventStoreRepository;
    $projector = new AccountBalanceProjector;

     // ربط الـ Projector بجميع الأحداث المتوقعة في السلسلة
    $bus->subscribe(AccountOpened::class, fn ($e) => $projector->handleAccountOpened($e));
    $bus->subscribe(MoneyDeposited::class, fn ($e) => $projector->handleMoneyDeposited($e));
    $bus->subscribe(MoneyWithdrawn::class, fn ($e) => $projector->handleMoneyWithdrawn($e));
    $bus->subscribe(AccountFrozen::class, fn ($e) => $projector->handleAccountFrozen($e));

    $accountId = AccountId::generate();

    // ==========================================
     // 2. سلسلة عمليات الدومين (The Business Steps)
    // ==========================================

     // الخطوة أ: فتح الحساب برصيد صفر
    $account = Account::open($accountId);

     // الخطوة ب: إيداع 1000 دولار ثم إيداع 500 دولار أخرى (الرصيد المفترض: 1500)
    $account->deposit(Money::fromInteger(1000));
    $account->deposit(Money::fromInteger(500));

     // الخطوة ج: سحب 300 دولار (الرصيد المفترض المتبقي: 1200)
    $account->withdraw(Money::fromInteger(300));

     // الخطوة د: تجميد الحساب للحماية
    $account->freeze();

    // ==========================================
     // 3. الحفظ والنشر (Persist & Publish)
    // ==========================================
     // نجلب كل الأحداث المتولدة من هذه الرحلة بالترتيب التاريخي الصارم
    $eventsToPublish = $account->releaseEvents();

     // نتأكد أن الرحلة أنتجت 5 أحداث متتالية (Opened -> Deposited -> Deposited -> Withdrawn -> Frozen)
    expect($eventsToPublish)->toHaveCount(5);

    $version = 1;
    foreach ($eventsToPublish as $event) {
         // تجهيز مصفوفة البيانات لكل نوع حدث ليتم تحويله لـ JSON بشكل صحيح
        $eventData = match (get_class($event)) {
            AccountOpened::class => ['accountId' => (string) $accountId],
            AccountFrozen::class => ['accountId' => (string) $accountId],
            MoneyDeposited::class => [
                'accountId' => (string) $accountId,
                'money' => ['amount' => $event->money->amount()],
            ],
            MoneyWithdrawn::class => [
                'accountId' => (string) $accountId,
                'money' => ['amount' => $event->money->amount()],
            ],
        };

         // حفظ الحدث في الـ Event Store (جدول الحقائق)
        DB::table('stored_events')->insert([
            'aggregate_id' => (string) $accountId,
            'event_type' => get_class($event),
            'event_data' => json_encode($eventData),
            'version' => $version,
        ]);

         // نشر الحدث ليدع الـ Projector يقوم بمهامه
        $bus->publish($event);
        $version++;
    }

    // ==========================================
     // 4. التحقق من جانب القراءة (Read Model Assertions)
    // ==========================================
    // نتأكد أن جدول القراءة السريع يعكس الحالة النهائية بدقة متناهية
    $this->assertDatabaseHas('account_balances', [
        'account_id' => (string) $accountId,
        'balance' => 1200, // 1000 + 500 - 300 = 1200
        'is_frozen' => true, // الحساب يجب أن يظهر مجمداً في لوحة التحكم
    ]);

    // نتأكد أن لدينا 5 أحداث مخزنة في الـ Event Store
    $this->assertDatabaseCount('stored_events', 5);

    // ==========================================
    // 5. التحقق من آلة الزمن (Reconstitution & Business Rules)
    // ==========================================
    // نقوم بجلب الحساب وإعادة بنائه من الصفر باستخدام الـ Repository
    $reconstitutedAccount = $repository->getById($accountId);

    // نتحقق أن الكائن الحي الذي تم إحياؤه يحمل البيانات الصحيحة والـ Version النهائي
    expect($reconstitutedAccount->balance()->amount())->toBe(1200);
    expect($reconstitutedAccount->isFrozen())->toBeTrue();
    expect($reconstitutedAccount->getVersion())->toBe(5);

    // نتحقق من أن قوانين العمل (Business Rules) لا زالت صارمة ومحمية بعد الإحياء:
    // محاولة الإيداع في هذا الكائن المسترجع يجب أن تفشل لأن حالته التاريخية تقول أنه "مجمّد"
    expect(fn () => $reconstitutedAccount->deposit(Money::fromInteger(100)))
        ->toThrow(AccountIsFrozen::class);
});
