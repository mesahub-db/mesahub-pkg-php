<?php

declare(strict_types=1);

namespace Mesahub\Errors;

class AuthorizationException extends MesahubException
{
    public function __construct(string $message = 'Insufficient permissions')
    {
        parent::__construct('AUTHZ_ERROR', 403, $message);
    }
}
