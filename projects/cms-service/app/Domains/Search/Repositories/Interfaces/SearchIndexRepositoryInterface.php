<?php

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\IndexEntryDTO;
use App\Models\SearchIndex;

interface SearchIndexRepositoryInterface
{
    /**
     * إنشاء أو تحديث سجل الفهرسة لـ entry معين ولغة معينة
     */
    public function upsert(IndexEntryDTO $dto): SearchIndex;

    /**
     * حذف كل سجلات الفهرسة لـ entry معين (عند حذف الـ entry)
     */
    public function deleteByEntryId(int $entryId): void;

    /**
     * حذف سجل فهرسة محدد بلغة محددة
     */
    public function deleteByEntryAndLanguage(int $entryId, string $language): void;

    /**
     * هل يوجد سجل فهرسة لهذا الـ entry وهذه اللغة؟
     */
    public function existsForEntry(int $entryId, string $language): bool;
}
