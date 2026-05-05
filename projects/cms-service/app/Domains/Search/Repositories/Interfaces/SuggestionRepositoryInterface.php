<?php

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\SuggestionQueryDTO;

interface SuggestionRepositoryInterface
{
    /**
     * البحث بـ prefix وإرجاع suggestions مرتبة حسب الـ score
     *
     * @return array<int, object{keyword: string, search_count: int, score: float}>
     */
    public function findByPrefix(SuggestionQueryDTO $dto): array;

    /**
     * إنشاء أو تحديث suggestion بعد كل بحث
     */
    public function upsertFromSearch(
        int $projectId,
        string $keyword,
        string $language
    ): void;

    /**
     * زيادة click_count عند الضغط على نتيجة بحث
     */
    public function incrementClickCount(
        int $projectId,
        string $keyword,
        string $language
    ): void;

    /**
     * بناء الـ suggestions table من user_search_logs الموجودة
     * يُستخدم في أمر إعادة البناء
     *
     * @return array{processed: int, upserted: int}
     */
    public function buildFromSearchLogs(int $projectId): array;
}
