<?php

namespace App\Domains\Search\DTOs;

class SearchResultItemDTO
{
    public function __construct(
        public readonly int $entryId,
        public readonly int $dataTypeId,
        public readonly int $projectId,
        public readonly string $language,
        public readonly ?string $title,
        public readonly ?string $snippet,   // مقطع من المحتوى حول الكلمة المطلوبة
        public readonly string $status,
        public readonly float $score,     // درجة الـ relevance
        public readonly ?string $publishedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId,
            'data_type_id' => $this->dataTypeId,
            'project_id' => $this->projectId,
            'language' => $this->language,
            'title' => $this->title,
            'snippet' => $this->snippet,
            'status' => $this->status,
            'score' => $this->score,
            'published_at' => $this->publishedAt,
        ];
    }
}
