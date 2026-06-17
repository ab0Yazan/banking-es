<?php

namespace App\Domain\Account\Exceptions;

use RuntimeException;

final class InsufficientBalance extends RuntimeException
{
    protected $message = 'Insufficient balance.';
}
