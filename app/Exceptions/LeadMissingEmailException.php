<?php

namespace App\Exceptions;

use RuntimeException;

class LeadMissingEmailException extends RuntimeException
{
    public function __construct(string $message = 'Enter a valid lead email before saving the quote.')
    {
        parent::__construct($message);
    }
}
