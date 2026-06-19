<?php

namespace App\Domain\Shared;

use App\Domain\Shared\Events\DomainEvent;

abstract class AggregateRoot
{
    /**
     * @var DomainEvent[]
     */
    protected array $recordedEvents = [];

    protected int $version = 0;

    final protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;

        $this->apply($event);

        $this->version++;
    }

    public static function reconstitute(array $storedEvents): static
    {
        $instance = new static;

        foreach ($storedEvents as $storedEvent) {
            $eventClass = $storedEvent->event_type;

            if (method_exists($eventClass, 'fromArray')) {
                $event = $eventClass::fromArray($storedEvent->event_data);
            } else {
                $event = new $eventClass(...$storedEvent->event_data);
            }

            $instance->apply($event);

            $instance->version++;
        }

        return $instance;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return DomainEvent[]
     */
    final public function releaseEvents(): array
    {
        $events = $this->recordedEvents;

        $this->recordedEvents = [];

        return $events;
    }

    abstract protected function apply(DomainEvent $event): void;
}
