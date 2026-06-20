<?php

namespace App\Domain\Account\Exceptions;

use Exception;

final class CardNotFound extends Exception
{
    protected $message = 'Card not found.';
}
