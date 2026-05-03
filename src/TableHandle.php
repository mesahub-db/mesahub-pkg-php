<?php

declare(strict_types=1);

namespace Mesahub;

use Mesahub\Types\ExecResult;
use Mesahub\Types\QueryResult;

class TableHandle
{
    private string $tbl;

    public function __construct(
        string $tableName,
        private readonly \Closure $queryFn,
        private readonly \Closure $execFn,
        private readonly \Closure $writeQueryFn,
    ) {
        $this->tbl = self::quoteIdent($tableName);
    }

    public function find(
        ?array $where   = null,
        ?array $select  = null,
        ?array $orderBy = null,
        ?int   $limit   = null,
        ?int   $offset  = null,
    ): array {
        [$sql, $bindings] = $this->buildSelect($where, $select, $orderBy, $limit, $offset);
        $result = ($this->queryFn)($sql, $bindings);
        return $result->rows;
    }

    public function findOne(
        ?array $where   = null,
        ?array $select  = null,
        ?array $orderBy = null,
    ): ?array {
        $rows = $this->find(where: $where, select: $select, orderBy: $orderBy, limit: 1);
        return $rows[0] ?? null;
    }

    public function count(?array $where = null): int
    {
        [$whereSql, $bindings] = WhereBuilder::build($where);
        $whereClause = $whereSql ? " WHERE {$whereSql}" : '';
        $sql         = "SELECT COUNT(*) AS \"_count\" FROM {$this->tbl}{$whereClause}";
        $result      = ($this->queryFn)($sql, $bindings);
        $row         = $result->rows[0] ?? [];
        return (int)($row['_count'] ?? $row['COUNT(*)'] ?? 0);
    }

    public function insert(array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('insert() called with empty data array');
        }
        $keys         = array_keys($data);
        $cols         = implode(', ', array_map([self::class, 'quoteIdent'], $keys));
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $bindings     = array_values($data);
        $sql          = "INSERT INTO {$this->tbl} ({$cols}) VALUES ({$placeholders}) RETURNING *";
        $result       = ($this->writeQueryFn)($sql, $bindings);
        if (empty($result->rows)) {
            throw new \RuntimeException('insert() returned no row from RETURNING *');
        }
        return $result->rows[0];
    }

    public function insertMany(array $rows, string $onConflict = ''): ExecResult
    {
        if (empty($rows)) {
            throw new \InvalidArgumentException('insertMany() called with empty rows');
        }
        $keys = array_keys($rows[0]);
        if (empty($keys)) {
            throw new \InvalidArgumentException('insertMany() first row has no columns');
        }
        $cols           = implode(', ', array_map([self::class, 'quoteIdent'], $keys));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($keys), '?')) . ')';
        $conflict       = match ($onConflict) {
            'ignore'  => ' OR IGNORE',
            'replace' => ' OR REPLACE',
            default   => '',
        };
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $rowPlaceholder));
        $bindings        = [];
        foreach ($rows as $row) {
            foreach ($keys as $k) {
                $bindings[] = $row[$k] ?? null;
            }
        }
        $sql = "INSERT{$conflict} INTO {$this->tbl} ({$cols}) VALUES {$allPlaceholders}";
        return ($this->execFn)($sql, $bindings);
    }

    public function update(array $where, array $set): ExecResult
    {
        if (empty($set)) {
            throw new \InvalidArgumentException('update() called with empty set array');
        }
        $setKeys     = array_keys($set);
        $setClauses  = implode(', ', array_map(fn($k) => self::quoteIdent($k) . ' = ?', $setKeys));
        $setBindings = array_values($set);

        [$whereSql, $whereBindings] = WhereBuilder::build($where);
        if (!$whereSql) {
            throw new \InvalidArgumentException('update() requires a non-empty where clause');
        }
        $sql = "UPDATE {$this->tbl} SET {$setClauses} WHERE {$whereSql}";
        return ($this->execFn)($sql, array_merge($setBindings, $whereBindings));
    }

    public function delete(array $where): ExecResult
    {
        [$whereSql, $bindings] = WhereBuilder::build($where);
        if (!$whereSql) {
            throw new \InvalidArgumentException('delete() requires a non-empty where clause');
        }
        $sql = "DELETE FROM {$this->tbl} WHERE {$whereSql}";
        return ($this->execFn)($sql, $bindings);
    }

    private function buildSelect(
        ?array $where,
        ?array $select,
        ?array $orderBy,
        ?int   $limit,
        ?int   $offset,
    ): array {
        $selectCols = $select
            ? implode(', ', array_map([self::class, 'quoteIdent'], $select))
            : '*';

        [$whereSql, $bindings] = WhereBuilder::build($where);
        $whereClause = $whereSql ? " WHERE {$whereSql}" : '';

        $orderClause = '';
        if (!empty($orderBy)) {
            $parts = array_map(function (array $o): string {
                $col = self::quoteIdent($o['column']);
                $dir = strtoupper($o['direction'] ?? 'ASC');
                return "{$col} {$dir}";
            }, $orderBy);
            $orderClause = ' ORDER BY ' . implode(', ', $parts);
        }

        $limitClause  = $limit  !== null ? " LIMIT {$limit}"   : '';
        $offsetClause = $offset !== null ? " OFFSET {$offset}" : '';

        $sql = "SELECT {$selectCols} FROM {$this->tbl}{$whereClause}{$orderClause}{$limitClause}{$offsetClause}";
        return [$sql, $bindings];
    }

    private static function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
