<?php

namespace App\Domain\Account;

use App\Domain\Account\Exceptions\CardLimitExceeded;
use App\Domain\Account\Exceptions\CardIsExpired;

final class DebitCard
{
    public function __construct(
        public string $cardNumber,
        public int $dailyLimit,
        public int $totalWithdrawnToday,
        public bool $isExpired = false
    ) {}

    public function verifyCanWithdraw(int $amount): void
    {
        if ($this->isExpired) {
            throw new CardIsExpired("هذه البطاقة منتهية الصلاحية!");
        }

        if (($this->totalWithdrawnToday + $amount) > $this->dailyLimit) {
            throw new CardLimitExceeded("تم تجاوز حد السحب اليومي المسموح به لهذه البطاقة!");
        }
    }
}