<?php

namespace App\Domain\Shared;

use App\Domain\Shared\Events\DomainEvent;

abstract class AggregateRoot
{
    /**
     * @var DomainEvent[]
     */
    private array $recordedEvents = [];

    final protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;

        $this->apply($event);
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
