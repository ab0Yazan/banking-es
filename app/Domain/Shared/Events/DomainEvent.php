<?php

namespace App\Domain\Shared\Events;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}