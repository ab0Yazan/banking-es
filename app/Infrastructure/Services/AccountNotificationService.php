<?php

namespace App\Infrastructure\Services;

class AccountNotificationService implements NotificationServiceInterface
{
    public function sendSms(string $accountId, string $message): bool
    {
        log("Sending SMS to account {$accountId}: {$message}");

        return true;
    }
}
