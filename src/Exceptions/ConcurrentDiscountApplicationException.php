<?php

namespace Hipster\UserDiscounts\Exceptions;

use Exception;

class ConcurrentDiscountApplicationException extends Exception
{
    public function __construct(string $message = "Concurrent discount application failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

