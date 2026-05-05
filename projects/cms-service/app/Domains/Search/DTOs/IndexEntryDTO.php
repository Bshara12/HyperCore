<?php

namespace App\Domains\Search\DTOs;

class IndexEntryDTO
{
    public function __construct(
        public readonly int $entryId,
        public readonly int $dataTypeId,
        public readonly int $projectId,
        public readonly string $language,
        public readonly ?string $title,
        public readonly ?string $content,
        public readonly ?array $meta,
        public readonly string $status,
        public readonly ?string $publishedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            entryId: $data['entry_id'],
            dataTypeId: $data['data_type_id'],
            projectId: $data['project_id'],
            language: $data['language'] ?? 'en',
            title: $data['title'] ?? null,
            content: $data['content'] ?? null,
            meta: $data['meta'] ?? null,
            status: $data['status'] ?? 'published',
            publishedAt: $data['published_at'] ?? null,
        );
    }
}
