<?php

namespace App\Infrastructure\EventStore;

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use Illuminate\Support\Facades\DB;

final class EventStoreRepository
{
    public function persist(Account $account): void
    {
        $events = $account->releaseEvents();

        foreach ($events as $event) {
            DB::table('stored_events')->insert([
                'aggregate_id' => (string) $account->id(),
                'event_type' => get_class($event),
                'event_data' => json_encode($this->serializeEvent($event)),
                'version' => $account->getVersion(),
            ]);
        }
    }

    public function getById(AccountId $accountId): Account
    {
        $rows = DB::table('stored_events')
            ->where('aggregate_id', (string) $accountId)
            ->orderBy('version', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            throw new \Exception('Account not found.');
        }

        $storedEvents = $rows->map(function ($row) {
            return (object) [
                'event_type' => $row->event_type,
                'event_data' => $this->deserializeEvent($row->event_type, json_decode($row->event_data, true)),
            ];
        })->toArray();

        return Account::reconstitute($storedEvents);
    }

    private function serializeEvent(object $event): array
    {
        return array_filter((array) $event);
    }

    private function deserializeEvent(string $type, array $data): array
    {
        return $data;
    }
}
