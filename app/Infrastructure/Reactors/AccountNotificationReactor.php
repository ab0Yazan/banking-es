<?php

namespace App\Infrastructure\Reactors;

use App\Domain\Account\Events\MoneyWithdrawn;
use App\Infrastructure\Services\NotificationServiceInterface;

final class AccountNotificationReactor
{
    public function __construct(
        private readonly NotificationServiceInterface $notificationService
    ) {}

    public function handleMoneyWithdrawn(MoneyWithdrawn $event): void
    {
        $this->notificationService->sendSms(
            $event->accountId->toString(),
            sprintf("تنبيه بنكي: تم سحب مبلغ %d من حسابك بنجاح.", $event->money->amount())
        );
    }
}