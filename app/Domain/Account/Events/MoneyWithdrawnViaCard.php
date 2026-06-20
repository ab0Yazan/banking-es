<?php

namespace App\Domain\Account\Events;

use App\Domain\Account\AccountId;
use App\Domain\Account\Money;
use App\Domain\Shared\Events\Event;

final readonly class MoneyWithdrawnViaCard extends Event
{
    public function __construct(
        public readonly AccountId $accountId,
        public readonly string $cardNumber,
        public readonly Money $money
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            AccountId::fromString($data['accountId']),
            $data['cardNumber'],
            Money::fromInteger($data['money']['amount'])
        );
    }
}
