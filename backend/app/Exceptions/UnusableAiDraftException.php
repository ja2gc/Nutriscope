<?php

namespace App\Exceptions;

use RuntimeException;

/** A completed, metered provider request whose diagnosis output cannot be used. */
class UnusableAiDraftException extends RuntimeException
{
    public function __construct(string $message, public readonly array $usage)
    {
        parent::__construct($message);
    }
}
