<?php

namespace App\Domain\Account\Events;

use App\Domain\Account\AccountId;
use App\Domain\Shared\Events\Event;

final readonly class CardIssued extends Event
{
    public function __construct(
        public readonly AccountId $accountId,
        public readonly string $cardNumber,
        public readonly int $dailyLimit     
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            AccountId::fromString($data['accountId']),
            $data['cardNumber'],
            $data['dailyLimit']
        );
    }
}
