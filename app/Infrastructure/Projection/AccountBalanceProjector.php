<?php

namespace App\Infrastructure\Projection;

use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use Illuminate\Support\Facades\DB;

final class AccountBalanceProjector
{
    public function handleAccountOpened(AccountOpened $event): void
    {
        DB::table('account_balances')->insert([
            'account_id' => (string) $event->accountId,
            'balance' => 0,
            'is_frozen' => false,
        ]);
    }

    public function handleMoneyDeposited(MoneyDeposited $event): void
    {
        DB::table('account_balances')
            ->where('account_id', (string) $event->accountId)
            ->increment('balance', $event->money->amount());
    }

    public function handleMoneyWithdrawn(MoneyWithdrawn $event): void
    {
        DB::table('account_balances')
            ->where('account_id', (string) $event->accountId)
            ->decrement('balance', $event->money->amount());
    }

    public function handleAccountFrozen(AccountFrozen $event): void
    {
        DB::table('account_balances')
            ->where('account_id', (string) $event->accountId)
            ->update(['is_frozen' => true]);
    }
}
