<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when attempting to modify an immutable (issued) invoice.
 */
class InvoiceImmutableException extends Exception
{
    public function __construct(string $message = 'Invoice cannot be modified after being issued.')
    {
        parent::__construct($message);
    }
}
