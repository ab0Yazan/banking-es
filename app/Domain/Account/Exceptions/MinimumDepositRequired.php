<?php

namespace App\Domain\Account\Exceptions;

use RuntimeException;

final class MinimumDepositRequired extends RuntimeException
{
    protected $message = 'Minimum deposit required.';
}
