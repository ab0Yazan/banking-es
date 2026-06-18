<?php

namespace App\Domain\Account\Events;

use App\Domain\Account\AccountId;
use App\Domain\Shared\Events\Event;

final readonly class AccountOpened extends Event
{
    public function __construct(
        public readonly AccountId $id
    ) {}
}
