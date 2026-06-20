<?php

namespace App\Domain\Account\Exceptions;

use Exception;

final class CardLimitExceeded extends Exception
{
    protected $message = 'Action denied. The card limit has been exceeded.';
}
