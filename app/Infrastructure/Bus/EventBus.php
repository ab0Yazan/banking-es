<?php

namespace App\Infrastructure\Bus;

use App\Domain\Shared\Events\DomainEvent;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;  
}