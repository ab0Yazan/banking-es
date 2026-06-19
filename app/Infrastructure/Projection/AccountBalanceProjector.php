<?php

namespace App\Infrastructure\Projection;

use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use Illuminate\Support\Facades\DB;

final class AccountBalanceProjector
{
    public function handleAccountOpened(AccountOpened $event, int $version): void
    {
        DB::transaction(function () use ($event, $version) {
            $exists = DB::table('account_balances')
                ->where('account_id', $event->accountId->toString())
                ->lockForUpdate()
                ->exists();

            if ($exists) return;

            DB::table('account_balances')->insert([
                'account_id' => $event->accountId->toString(),
                'balance' => 0,
                'total_deposited_ever' => 0,
                'is_frozen' => false,
                'last_version' => $version,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        });
    }

    public function handleMoneyDeposited(MoneyDeposited $event, int $version): void
    {
        DB::transaction(function () use ($event, $version) {
            $current = DB::table('account_balances')
                ->where('account_id', $event->accountId->toString())
                ->lockForUpdate() 
                ->first();

            if (!$current || $current->last_version >= $version) {
                return; 
            }

            DB::table('account_balances')
                ->where('account_id', $event->accountId->toString())
                ->update([
                    'balance' => $current->balance + $event->money->amount(),
                    'total_deposited_ever' => $current->total_deposited_ever + $event->money->amount(), 
                    'last_version' => $version,
                    'updated_at' => now()
                ]);
        });
    }

    public function handleMoneyWithdrawn(MoneyWithdrawn $event, int $version): void
    {
        DB::transaction(function () use ($event, $version) {
            $current = DB::table('account_balances')
                ->where('account_id', $event->accountId->toString())
                ->lockForUpdate()
                ->first();

            if (!$current || $current->last_version >= $version) {
                return;
            }

            DB::table('account_balances')
                ->where('account_id', $event->accountId->toString())
                ->update([
                    'balance' => $current->balance - $event->money->amount(),
                    'last_version' => $version,
                    'updated_at' => now()
                ]);
        });
    }

    public function handleAccountFrozen(AccountFrozen $event, int $version): void
    {
        DB::transaction(function () use ($event, $version) {
            $current = DB::table('account_balances')
                ->where('account_id', $event->accountId->toString())
                ->lockForUpdate()
                ->first();

            if (!$current || $current->last_version >= $version) {
                return;
            }

            DB::table('account_balances')
                ->where('account_id', $event->accountId->toString())
                ->update([
                    'is_frozen' => true,
                    'last_version' => $version,
                    'updated_at' => now()
                ]);
        });
    }
}