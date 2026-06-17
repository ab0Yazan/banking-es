<?php

namespace App\Domain\Account\Events;

use App\Domain\Account\AccountId;
use App\Domain\Account\Money;
use App\Domain\Shared\Events\Event;

final readonly class MoneyWithdrawn extends Event
{
    public function __construct(
        public AccountId $accountId,
        public Money $money,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        parent::__construct(
            $occurredAt ?? new \DateTimeImmutable
        );
    }
}
