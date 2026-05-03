<?php

declare(strict_types=1);

namespace Mesahub\Errors;

class NotFoundException extends MesahubException
{
    public function __construct(string $resource)
    {
        parent::__construct('NOT_FOUND', 404, "{$resource} not found");
    }
}
