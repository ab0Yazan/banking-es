<?php

namespace App\Domain\Shared\Events;

abstract readonly class Event implements DomainEvent
{
    public function __construct(
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}