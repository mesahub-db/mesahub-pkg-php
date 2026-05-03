<?php

declare(strict_types=1);

namespace Mesahub;

use Mesahub\Types\ExecResult;
use Mesahub\Types\FileRecord;
use Mesahub\Types\QueryResult;

class DatabaseHandle
{
    public readonly DatabaseFiles $files;

    public function __construct(
        private readonly string $ref,
        private readonly MesahubClient $client,
    ) {
        $this->files = new DatabaseFiles($ref, $client);
    }

    public function query(string $sql, array $bindings = []): QueryResult
    {
        return $this->client->queryRaw($this->ref, $sql, $bindings);
    }

    public function exec(string $sql, array $bindings = []): ExecResult
    {
        return $this->client->execRaw($this->ref, $sql, $bindings);
    }

    public function table(string $tableName): TableHandle
    {
        return new TableHandle(
            $tableName,
            fn(string $sql, array $b) => $this->query($sql, $b),
            fn(string $sql, array $b) => $this->exec($sql, $b),
            fn(string $sql, array $b) => $this->client->execRows($this->ref, $sql, $b),
        );
    }
}

class DatabaseFiles
{
    public function __construct(
        private readonly string $ref,
        private readonly MesahubClient $client,
    ) {}

    public function list(?int $limit = null, ?int $offset = null, ?string $folderPrefix = null): array
    {
        return $this->client->filesList($this->ref, $limit, $offset, $folderPrefix);
    }

    public function upload(string $data, string $filename, string $contentType = 'application/octet-stream'): FileRecord
    {
        return $this->client->filesUpload($this->ref, $data, $filename, $contentType);
    }

    public function download(string $fileId): string
    {
        return $this->client->filesDownload($this->ref, $fileId);
    }

    public function delete(string $fileId): void
    {
        $this->client->filesDelete($this->ref, $fileId);
    }
}
