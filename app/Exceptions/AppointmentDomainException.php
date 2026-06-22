<?php

namespace App\Exceptions;

use RuntimeException;

class AppointmentDomainException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $httpStatus = 409,
        public array $errors = [],
    ) {
        parent::__construct($message);
    }
}
