<?php

declare(strict_types=1);

namespace Mesahub\Errors;

class AuthenticationException extends MesahubException
{
    public function __construct(string $message = 'Authentication failed')
    {
        parent::__construct('AUTH_ERROR', 401, $message);
    }
}
