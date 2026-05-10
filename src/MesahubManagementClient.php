<?php

declare(strict_types=1);

namespace Mesahub;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;

/**
 * MesahubManagementClient — management-plane SDK for MesaHub.
 *
 * Talks to the MesaHub dashboard (Next.js) using a `shs_` API key.
 * Provides CRUD for databases, buckets, and API keys.
 *
 * Usage:
 * ```php
 * $mgmt = new \Mesahub\MesahubManagementClient(
 *     dashboardUrl: 'https://www.mesahub.app',
 *     apiKey: 'shs_...',
 * );
 *
 * $dbs = $mgmt->listDatabases();
 * $db  = $mgmt->createDatabase('my-app-db');
 * $mgmt->deleteDatabase($db['id']);
 *
 * $keys   = $mgmt->listApiKeys();
 * $result = $mgmt->createApiKey('ci-key');
 * echo $result['key']; // raw token — shown only once
 * $mgmt->revokeApiKey($result['id']);
 * ```
 */
class MesahubManagementClient
{
    private HttpClient $http;

    public function __construct(
        string $dashboardUrl,
        string $apiKey,
        float  $timeout = 30.0,
    ) {
        $base = rtrim($dashboardUrl, '/');
        $this->http = new HttpClient([
            'base_uri' => $base,
            'headers'  => ['Authorization' => "Bearer {$apiKey}"],
            'timeout'  => $timeout,
        ]);
    }

    // ── internal ──────────────────────────────────────────────────────────────

    /** @return mixed */
    private function req(string $method, string $path, array $body = null): mixed
    {
        try {
            $opts = ['headers' => ['Content-Type' => 'application/json']];
            if ($body !== null) {
                $opts['json'] = $body;
            }
            $res = $this->http->request($method, $path, $opts);
            if ($res->getStatusCode() === 204) {
                return null;
            }
            return json_decode((string)$res->getBody(), associative: true);
        } catch (BadResponseException $e) {
            $body = (string)$e->getResponse()->getBody();
            $data = json_decode($body, associative: true);
            $msg  = $data['error'] ?? ('HTTP ' . $e->getCode());
            throw new \RuntimeException($msg, $e->getCode(), $e);
        }
    }

    // ── Databases ─────────────────────────────────────────────────────────────

    /** List all databases owned by the authenticated user. */
    public function listDatabases(): array
    {
        return $this->req('GET', '/api/user/databases') ?? [];
    }

    /** Get a single database by ID. */
    public function getDatabase(string $id): array
    {
        return $this->req('GET', "/api/user/databases/{$id}");
    }

    /**
     * Create a new database.
     *
     * @param string      $name        3–50 lowercase alphanumeric chars, dashes, or underscores.
     * @param string|null $description Optional human-readable description.
     */
    public function createDatabase(string $name, ?string $description = null): array
    {
        $body = ['name' => $name];
        if ($description !== null) {
            $body['description'] = $description;
        }
        return $this->req('POST', '/api/user/databases', $body);
    }

    /** Delete a database by ID. */
    public function deleteDatabase(string $id): void
    {
        $this->req('DELETE', "/api/user/databases/{$id}");
    }

    /** Update a database's name and/or description. */
    public function updateDatabase(string $id, ?string $name = null, ?string $description = null): array
    {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($description !== null) {
            $body['description'] = $description;
        }
        return $this->req('PATCH', "/api/user/databases/{$id}", $body);
    }

    /**
     * Export the given tables from a database as a SQLite binary.
     *
     * @param  string[] $tables   Table names to export (at least one required).
     * @param  string   $filename Optional filename hint.
     * @return string             Raw SQLite file bytes.
     */
    public function exportDatabase(string $id, array $tables, string $filename = ''): string
    {
        try {
            $body = ['tables' => $tables];
            if ($filename !== '') {
                $body['filename'] = $filename;
            }
            $res = $this->http->request('POST', "/api/user/databases/{$id}/export", [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => $body,
            ]);
            return (string)$res->getBody();
        } catch (BadResponseException $e) {
            $data = json_decode((string)$e->getResponse()->getBody(), associative: true);
            $msg  = $data['error'] ?? ('HTTP ' . $e->getCode());
            throw new \RuntimeException($msg, $e->getCode(), $e);
        }
    }

    /**
     * Phase 1 import: inspect a SQLite file and return its table list.
     * Does NOT modify the target database.
     *
     * @param  resource|string $file     File resource or path.
     * @param  string          $filename Filename sent in multipart.
     * @return array{tables: string[]}
     */
    public function importDatabaseInspect(string $id, mixed $file, string $filename = 'import.db'): array
    {
        return $this->uploadImport($id, $file, $filename, null);
    }

    /**
     * Phase 2 import: copy the selected tables from a SQLite file into the database.
     *
     * @param  resource|string $file     File resource or path.
     * @param  string[]        $tables   Tables to import.
     * @param  string          $filename Filename sent in multipart.
     * @return array{rows_imported?: int, size_bytes?: int}
     */
    public function importDatabase(string $id, mixed $file, array $tables, string $filename = 'import.db'): array
    {
        return $this->uploadImport($id, $file, $filename, $tables);
    }

    /** @param string[]|null $tables */
    private function uploadImport(string $id, mixed $file, string $filename, ?array $tables): array
    {
        if (is_string($file)) {
            $file = fopen($file, 'r');
        }
        try {
            $multipart = [
                ['name' => 'file', 'contents' => $file, 'filename' => $filename],
            ];
            if ($tables !== null) {
                $multipart[] = ['name' => 'tables', 'contents' => json_encode($tables)];
            }
            $res = $this->http->request('POST', "/api/user/databases/{$id}/import", [
                'multipart' => $multipart,
            ]);
            return json_decode((string)$res->getBody(), associative: true) ?? [];
        } catch (BadResponseException $e) {
            $data = json_decode((string)$e->getResponse()->getBody(), associative: true);
            $msg  = $data['error'] ?? ('HTTP ' . $e->getCode());
            throw new \RuntimeException($msg, $e->getCode(), $e);
        }
    }

    // ── Buckets ───────────────────────────────────────────────────────────────

    /** List all buckets owned by the authenticated user. */
    public function listBuckets(): array
    {
        return $this->req('GET', '/api/user/buckets') ?? [];
    }

    /** Get a single bucket by ID. */
    public function getBucket(string $id): array
    {
        return $this->req('GET', "/api/user/buckets/{$id}");
    }

    /** Create a new bucket. */
    public function createBucket(string $name, ?string $description = null): array
    {
        $body = ['name' => $name];
        if ($description !== null) {
            $body['description'] = $description;
        }
        return $this->req('POST', '/api/user/buckets', $body);
    }

    /** Delete a bucket by ID. */
    public function deleteBucket(string $id): void
    {
        $this->req('DELETE', "/api/user/buckets/{$id}");
    }

    /** Update a bucket's name and/or description. */
    public function updateBucket(string $id, ?string $name = null, ?string $description = null): array
    {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($description !== null) {
            $body['description'] = $description;
        }
        return $this->req('PATCH', "/api/user/buckets/{$id}", $body);
    }

    // ── API Keys ──────────────────────────────────────────────────────────────

    /** List all API keys for the authenticated user. */
    public function listApiKeys(): array
    {
        return $this->req('GET', '/api/user/api-keys') ?? [];
    }

    /**
     * Create a new API key.
     *
     * The ``key`` field in the returned array is the raw token — only returned
     * once and not stored by the server.
     *
     * @param  string[]|null $scopes Defaults to ["all:w"].
     * @return array{id: string, name: string, key: string}
     */
    public function createApiKey(string $name, ?array $scopes = null): array
    {
        return $this->req('POST', '/api/user/api-keys', [
            'name'   => $name,
            'scopes' => $scopes ?? ['all:w'],
        ]);
    }

    /** Revoke an API key by ID. */
    public function revokeApiKey(string $id): void
    {
        $this->req('DELETE', "/api/user/api-keys/{$id}");
    }
}
