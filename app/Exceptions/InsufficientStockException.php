<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected array $details;

    public function __construct(string $message = "Insufficient stock", array $details = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
