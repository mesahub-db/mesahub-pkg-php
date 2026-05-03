<?php

declare(strict_types=1);

namespace Mesahub\Types;

readonly class QueryResult
{
    public function __construct(
        public array $rows,
        public array $columns,
        public int $rowCount,
        public float|null $queryDurationMs,
    ) {}
}
