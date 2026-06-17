<?php

namespace App\Infrastructure\Bus;

use App\Domain\Shared\Events\DomainEvent;

final class InMemoryEventBus implements EventBus
{
    private array $listeners = [];

    public function subscribe(
        string $eventClass,
        callable $listener
    ): void {
        $this->listeners[$eventClass][] = $listener;
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {

            $listeners = $this->listeners[
                $event::class
            ] ?? [];

            foreach ($listeners as $listener) {
                $listener($event);
            }
        }
    }
}
