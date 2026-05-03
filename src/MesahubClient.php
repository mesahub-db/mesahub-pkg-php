<?php

declare(strict_types=1);

namespace Mesahub;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use Mesahub\Errors\MesahubException;
use Mesahub\Types\ExecResult;
use Mesahub\Types\FileRecord;
use Mesahub\Types\QueryResult;

class MesahubClient
{
    private HttpClient $http;
    private \Closure $pathQuery;
    private \Closure $pathExec;
    private \Closure $pathFiles;
    private \Closure $pathFileItem;

    public function __construct(
        string $apiKey,
        string $apiUrl,
        string $routePrefix = 'v1',
        float  $timeout     = 30.0,
    ) {
        $base = (string)preg_replace('#/(v1|api)/?$#', '', rtrim($apiUrl, '/'));

        $this->http = new HttpClient([
            'base_uri' => $base,
            'headers'  => ['Authorization' => "Bearer {$apiKey}"],
            'timeout'  => $timeout,
        ]);

        if ($routePrefix === 'api') {
            $this->pathQuery    = fn(string $ref)                     => "/api/db/{$ref}/query";
            $this->pathExec     = fn(string $ref)                     => "/api/db/{$ref}/exec";
            $this->pathFiles    = fn(string $ref)                     => "/api/db/{$ref}/files";
            $this->pathFileItem = fn(string $ref, string $id)        => "/api/db/{$ref}/files/{$id}";
        } else {
            $this->pathQuery    = fn(string $ref)                     => "/v1/query/{$ref}";
            $this->pathExec     = fn(string $ref)                     => "/v1/exec/{$ref}";
            $this->pathFiles    = fn(string $ref)                     => "/v1/files/{$ref}";
            $this->pathFileItem = fn(string $ref, string $id)        => "/v1/files/{$ref}/{$id}";
        }
    }

    public function query(string $ref, string $sql, array $bindings = []): QueryResult
    {
        return $this->queryRaw($ref, $sql, $bindings);
    }

    public function exec(string $ref, string $sql, array $bindings = []): ExecResult
    {
        return $this->execRaw($ref, $sql, $bindings);
    }

    public function db(string $ref): DatabaseHandle
    {
        return new DatabaseHandle($ref, $this);
    }

    /** @internal */
    public function queryRaw(string $ref, string $sql, array $bindings): QueryResult
    {
        $raw     = $this->post(($this->pathQuery)($ref), ['sql' => $sql, 'bindings' => $bindings]);
        $columns = isset($raw['headers'])
            ? array_column($raw['headers'], 'name')
            : ($raw['columns'] ?? []);
        $rows    = $raw['rows'] ?? [];
        return new QueryResult(
            rows:            $rows,
            columns:         $columns,
            rowCount:        count($rows),
            queryDurationMs: $raw['stat']['queryDurationMs'] ?? null,
        );
    }

    /** @internal */
    public function execRaw(string $ref, string $sql, array $bindings): ExecResult
    {
        $raw  = $this->post(($this->pathExec)($ref), ['sql' => $sql, 'bindings' => $bindings]);
        $stat = $raw['stat'] ?? [];
        return new ExecResult(
            rowsAffected:    $raw['rowsAffected']   ?? $stat['rowsAffected']   ?? 0,
            lastInsertRowid: $raw['lastInsertRowid'] ?? null,
            queryDurationMs: $stat['queryDurationMs'] ?? null,
        );
    }

    /** @internal */
    public function execRows(string $ref, string $sql, array $bindings): QueryResult
    {
        $raw     = $this->post(($this->pathExec)($ref), ['sql' => $sql, 'bindings' => $bindings]);
        $columns = isset($raw['headers'])
            ? array_column($raw['headers'], 'name')
            : ($raw['columns'] ?? []);
        $rows    = $raw['rows'] ?? [];
        return new QueryResult(
            rows:            $rows,
            columns:         $columns,
            rowCount:        count($rows),
            queryDurationMs: $raw['stat']['queryDurationMs'] ?? null,
        );
    }

    /** @internal */
    public function filesList(string $ref, ?int $limit, ?int $offset, ?string $folderPrefix): array
    {
        $params = [];
        if ($limit        !== null) $params['limit']         = $limit;
        if ($offset       !== null) $params['offset']        = $offset;
        if ($folderPrefix !== null) $params['folder_prefix'] = $folderPrefix;
        return $this->get(($this->pathFiles)($ref), $params);
    }

    /** @internal */
    public function filesUpload(string $ref, string $data, string $filename, string $contentType): FileRecord
    {
        try {
            $resp = $this->http->post(($this->pathFiles)($ref), [
                'multipart' => [[
                    'name'     => 'file',
                    'contents' => $data,
                    'filename' => $filename,
                    'headers'  => ['Content-Type' => $contentType],
                ]],
            ]);
            return FileRecord::fromArray(json_decode((string)$resp->getBody(), true));
        } catch (BadResponseException $e) {
            $this->throwFromGuzzle($e);
        }
    }

    /** @internal */
    public function filesDownload(string $ref, string $fileId): string
    {
        try {
            $resp = $this->http->get(($this->pathFileItem)($ref, $fileId));
            return (string)$resp->getBody();
        } catch (BadResponseException $e) {
            $this->throwFromGuzzle($e);
        }
    }

    /** @internal */
    public function filesDelete(string $ref, string $fileId): void
    {
        try {
            $this->http->delete(($this->pathFileItem)($ref, $fileId));
        } catch (BadResponseException $e) {
            $this->throwFromGuzzle($e);
        }
    }

    public static function parseMesahubUrl(string $raw): array
    {
        if (!str_starts_with($raw, 'mh://')) {
            throw new \InvalidArgumentException(
                "Invalid MESAHUB_URL: must start with mh:// (got: " . substr($raw, 0, 30) . ")"
            );
        }

        $parsed = parse_url(str_replace('mh://', 'http://', $raw));
        $host   = $parsed['host'] ?? '';

        if ($host === 'local') {
            throw new \InvalidArgumentException(
                'mh://local/... is the embedded mode placeholder — it must be resolved ' .
                'to a concrete URL by start.sh before the application starts.'
            );
        }

        $isPrivate = in_array($host, ['localhost', '127.0.0.1'], true)
            || !str_contains($host, '.')
            || str_ends_with($host, '.internal');

        $scheme   = $isPrivate ? 'http' : 'https';
        $portPart = isset($parsed['port']) ? ":{$parsed['port']}" : '';
        $apiUrl   = "{$scheme}://{$host}{$portPart}";

        $apiKey = urldecode($parsed['user'] ?? '');
        if ($apiKey === '') {
            throw new \InvalidArgumentException(
                'MESAHUB_URL must include an API key: mh://apikey@host/dbname'
            );
        }

        $dbName = ltrim($parsed['path'] ?? '', '/');
        if ($dbName === '') {
            throw new \InvalidArgumentException(
                'MESAHUB_URL must include a database name: mh://apikey@host/dbname'
            );
        }

        return [
            'api_url'      => $apiUrl,
            'api_key'      => $apiKey,
            'db_name'      => $dbName,
            'route_prefix' => $isPrivate ? 'api' : 'v1',
        ];
    }

    private function post(string $path, array $body): array
    {
        try {
            $resp = $this->http->post($path, ['json' => $body]);
            return json_decode((string)$resp->getBody(), true) ?? [];
        } catch (BadResponseException $e) {
            $this->throwFromGuzzle($e);
        }
    }

    private function get(string $path, array $params = []): array
    {
        try {
            $resp = $this->http->get($path, ['query' => $params]);
            return json_decode((string)$resp->getBody(), true) ?? [];
        } catch (BadResponseException $e) {
            $this->throwFromGuzzle($e);
        }
    }

    /** @return never */
    private function throwFromGuzzle(BadResponseException $e): void
    {
        $statusCode = $e->getResponse()->getStatusCode();
        $statusText = $e->getResponse()->getReasonPhrase();
        $body       = json_decode((string)$e->getResponse()->getBody(), true);
        throw MesahubException::fromResponse($statusCode, $statusText, $body);
    }
}
