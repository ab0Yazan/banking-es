<?php

namespace App\Infrastructure\Services;

interface NotificationServiceInterface
{
    public function sendSms(string $accountId, string $message): bool;
}