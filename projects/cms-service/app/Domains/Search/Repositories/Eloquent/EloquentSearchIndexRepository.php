<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\IndexEntryDTO;
use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Models\SearchIndex;

class EloquentSearchIndexRepository implements SearchIndexRepositoryInterface
{
    public function upsert(IndexEntryDTO $dto): SearchIndex
    {
        $record = SearchIndex::updateOrCreate(
            // ─── شرط البحث (المفتاح الفريد) ─────────────────────────
            [
                'entry_id' => $dto->entryId,
                'language' => $dto->language,
            ],
            // ─── البيانات المراد حفظها أو تحديثها ───────────────────
            [
                'data_type_id' => $dto->dataTypeId,
                'project_id' => $dto->projectId,
                'title' => $dto->title,
                'content' => $dto->content,
                // بدون json_encode يدوي: الموديل يُحوِّل meta بـ cast 'array'.
                // التشفير المزدوج القديم كان يُخزِّن JSON داخل JSON string.
                'meta' => $dto->meta ?: null,

                // النص المُطبَّع الذي يُطابقه FULLTEXT — إن لم يُمرَّر
                // فالـ saving hook في الموديل يبنيه من title/content/meta
                'search_text' => $dto->searchText,

                // كان NULL دائماً قبل الإصلاح → أي فلترة intent تُصفّر النتائج
                'data_type_slug' => $dto->dataTypeSlug,

                'status' => $dto->status,
                'published_at' => $dto->publishedAt,
            ]
        );

        return $record;
    }

    public function deleteByEntryId(int $entryId): void
    {
        SearchIndex::where('entry_id', $entryId)->delete();
    }

    public function deleteByEntryAndLanguage(int $entryId, string $language): void
    {
        SearchIndex::where('entry_id', $entryId)
            ->where('language', $language)
            ->delete();
    }

    public function existsForEntry(int $entryId, string $language): bool
    {
        return SearchIndex::where('entry_id', $entryId)
            ->where('language', $language)
            ->exists();
    }
}
