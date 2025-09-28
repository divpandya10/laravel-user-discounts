<?php

namespace Hipster\UserDiscounts\Exceptions;

use Exception;

class DiscountUsageLimitExceededException extends Exception
{
    public function __construct(string $message = "Discount usage limit exceeded", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

