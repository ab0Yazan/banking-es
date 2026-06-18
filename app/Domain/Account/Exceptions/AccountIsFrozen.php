<?php

namespace App\Domain\Account\Exceptions;

use Exception;

final class AccountIsFrozen extends Exception
{
    protected $message = 'Action denied. The bank account is currently frozen.';
}
