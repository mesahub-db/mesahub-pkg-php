<?php

declare(strict_types=1);

namespace Mesahub\Errors;

use RuntimeException;

class MesahubException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $statusCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(int $statusCode, string $statusText, mixed $body): static
    {
        $data    = is_array($body) ? $body : [];
        $code    = $data['code']    ?? 'UNKNOWN_ERROR';
        $message = $data['message'] ?? $statusText;
        return new static($code, $statusCode, $message, $data);
    }
}
