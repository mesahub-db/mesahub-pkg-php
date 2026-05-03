<?php

declare(strict_types=1);

namespace Mesahub;

class WhereBuilder
{
    public static function build(?array $clause): array
    {
        if (empty($clause)) {
            return ['', []];
        }

        $parts    = [];
        $bindings = [];

        foreach ($clause as $key => $op) {
            $col = self::quoteIdent($key);

            if ($op === null) {
                continue;
            }

            if (!is_array($op)) {
                $parts[]    = "{$col} = ?";
                $bindings[] = $op;
                continue;
            }

            if (array_key_exists('is_null', $op)) {
                $parts[] = "{$col} IS NULL";
            } elseif (array_key_exists('is_not_null', $op)) {
                $parts[] = "{$col} IS NOT NULL";
            } elseif (array_key_exists('eq', $op)) {
                $parts[]    = "{$col} = ?";
                $bindings[] = $op['eq'];
            } elseif (array_key_exists('ne', $op)) {
                $parts[]    = "{$col} != ?";
                $bindings[] = $op['ne'];
            } elseif (array_key_exists('gt', $op)) {
                $parts[]    = "{$col} > ?";
                $bindings[] = $op['gt'];
            } elseif (array_key_exists('gte', $op)) {
                $parts[]    = "{$col} >= ?";
                $bindings[] = $op['gte'];
            } elseif (array_key_exists('lt', $op)) {
                $parts[]    = "{$col} < ?";
                $bindings[] = $op['lt'];
            } elseif (array_key_exists('lte', $op)) {
                $parts[]    = "{$col} <= ?";
                $bindings[] = $op['lte'];
            } elseif (array_key_exists('like', $op)) {
                $parts[]    = "{$col} LIKE ?";
                $bindings[] = $op['like'];
            } elseif (array_key_exists('not_like', $op)) {
                $parts[]    = "{$col} NOT LIKE ?";
                $bindings[] = $op['not_like'];
            } elseif (array_key_exists('in', $op)) {
                $vals = $op['in'];
                if (empty($vals)) {
                    $parts[] = '1 = 0';
                } else {
                    $ph      = implode(', ', array_fill(0, count($vals), '?'));
                    $parts[] = "{$col} IN ({$ph})";
                    array_push($bindings, ...$vals);
                }
            } elseif (array_key_exists('not_in', $op)) {
                $vals = $op['not_in'];
                if (!empty($vals)) {
                    $ph      = implode(', ', array_fill(0, count($vals), '?'));
                    $parts[] = "{$col} NOT IN ({$ph})";
                    array_push($bindings, ...$vals);
                }
            } else {
                $parts[]    = "{$col} = ?";
                $bindings[] = $op;
            }
        }

        return [implode(' AND ', $parts), $bindings];
    }

    private static function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
