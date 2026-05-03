<?php

declare(strict_types=1);

namespace Mesahub\Errors;

class RateLimitException extends MesahubException
{
    public function __construct(int|null $retryAfter = null)
    {
        parent::__construct('RATE_LIMIT', 429, 'Rate limit exceeded', ['retry_after' => $retryAfter]);
    }
}
