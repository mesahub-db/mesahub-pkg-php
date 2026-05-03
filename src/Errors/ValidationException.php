<?php

declare(strict_types=1);

namespace Mesahub\Errors;

class ValidationException extends MesahubException
{
    public function __construct(string $message = 'Validation failed', array $details = [])
    {
        parent::__construct('VALIDATION_ERROR', 400, $message, $details);
    }
}
