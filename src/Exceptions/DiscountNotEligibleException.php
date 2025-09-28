<?php

namespace Hipster\UserDiscounts\Exceptions;

use Exception;

class DiscountNotEligibleException extends Exception
{
    public function __construct(string $message = "Discount is not eligible", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

