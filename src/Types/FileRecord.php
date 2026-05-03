<?php

declare(strict_types=1);

namespace Mesahub\Types;

readonly class FileRecord
{
    public function __construct(
        public string $id,
        public string $filename,
        public string|null $folderPath,
        public int $sizeBytes,
        public string|null $contentType,
        public string $url,
        public string $uploadedAt,
        public string|null $expiresAt,
        public string|null $metadata,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:           $data['id'],
            filename:     $data['filename'],
            folderPath:   $data['folder_path'] ?? null,
            sizeBytes:    (int)$data['size_bytes'],
            contentType:  $data['content_type'] ?? null,
            url:          $data['url'],
            uploadedAt:   $data['uploaded_at'],
            expiresAt:    $data['expires_at'] ?? null,
            metadata:     $data['metadata'] ?? null,
        );
    }
}
