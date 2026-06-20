<?php

namespace App\Domain\Account\Exceptions;

use Exception;

final class CardIsExpired extends Exception
{
    protected $message = 'Action denied. The card is currently expired.';
}
