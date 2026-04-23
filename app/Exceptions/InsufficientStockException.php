<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a stock deduction would drive inventory below zero.
 */
class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly string $productName,
        public readonly float $available,
        public readonly float $required,
    ) {
        parent::__construct(
            "Insufficient stock for '{$productName}'. Available: {$available}, required: {$required}."
        );
    }
}
