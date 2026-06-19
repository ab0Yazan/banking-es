<?php

namespace App\Infrastructure\Queries;

use App\Domain\Account\AccountId;
use App\Domain\Account\Events\MoneyDeposited;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class AccountDepositReportQuery
{
    public function execute(AccountId $accountId, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $events = DB::table('stored_events')
            ->where('aggregate_id', $accountId->toString())
            ->where('event_type', MoneyDeposited::class)

            ->whereBetween('created_at', [
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ])
            ->get();

        $totalDeposited = 0;

        foreach ($events as $storedEvent) {
            $data = json_decode($storedEvent->event_data, true);

            $totalDeposited += $data['money']['amount'];
        }

        return $totalDeposited;
    }
}
