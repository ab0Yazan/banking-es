<?php

namespace App\Infrastructure\EventStore;

use App\Models\EventStore;
use Illuminate\Support\Collection;

class EventStoreRepository
{
    public function append(array $events, string $aggregateId, string $aggregateType, int $startingVersion): void
    {
        foreach ($events as $i => $event) {
            EventStore::create([
                'aggregate_uuid' => $aggregateId,
                'aggregate_type' => $aggregateType,
                'event_type' => get_class($event),
                'event_data' => $event->toArray(),
                'version' => $startingVersion + $i + 1,
                'occurred_at' => now(),
            ]);
        }
    }

    public function load(string $aggregateId, string $aggregateType): Collection
    {
        return EventStore::query()
            ->where('aggregate_uuid', $aggregateId)
            ->where('aggregate_type', $aggregateType)
            ->orderBy('version')
            ->get();
    }
}
