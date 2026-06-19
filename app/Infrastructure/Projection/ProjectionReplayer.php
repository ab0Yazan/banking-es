<?php

namespace App\Infrastructure\Projection;

use Illuminate\Support\Facades\DB;

final class ProjectionReplayer
{
    public function __construct(
        private readonly AccountBalanceProjector $projector
    ) {}

    public function replayAll(): void
    {
        DB::table('account_balances')->truncate();

        $storedEvents = DB::table('stored_events')->orderBy('id', 'asc')->get();

        foreach ($storedEvents as $storedEvent) {
            $eventClass = $storedEvent->event_type;
            $eventData = json_decode($storedEvent->event_data, true);
            $event = $eventClass::fromArray($eventData);

            match ($eventClass) {
                \App\Domain\Account\Events\AccountOpened::class => $this->projector->handleAccountOpened($event, $storedEvent->version),
                \App\Domain\Account\Events\MoneyDeposited::class => $this->projector->handleMoneyDeposited($event, $storedEvent->version),
                \App\Domain\Account\Events\MoneyWithdrawn::class => $this->projector->handleMoneyWithdrawn($event, $storedEvent->version),
                \App\Domain\Account\Events\AccountFrozen::class => $this->projector->handleAccountFrozen($event, $storedEvent->version),
                default => null
            };
        }
    }
}