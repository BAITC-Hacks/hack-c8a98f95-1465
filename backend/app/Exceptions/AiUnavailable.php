<?php

namespace App\Exceptions;

use RuntimeException;

class AiUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }
}
