<?php

namespace Hipster\UserDiscounts\Exceptions;

use Exception;

class DiscountNotFoundException extends Exception
{
    public function __construct(string $message = "Discount not found", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

