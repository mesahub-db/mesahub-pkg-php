<?php

declare(strict_types=1);

namespace Mesahub\Types;

readonly class ExecResult
{
    public function __construct(
        public int $rowsAffected,
        public int|null $lastInsertRowid,
        public float|null $queryDurationMs,
    ) {}
}
