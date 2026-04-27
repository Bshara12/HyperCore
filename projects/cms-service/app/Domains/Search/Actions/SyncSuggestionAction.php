<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use Illuminate\Support\Facades\Log;

class SyncSuggestionAction
{
    public function __construct(
        private SuggestionRepositoryInterface $repository,
    ) {}

    /**
     * يُستدعى بعد كل بحث ناجح لتحديث الـ suggestions
     *
     * مهم: هذا يعمل بشكل async (Queue) في الـ production
     * لأنه لا يجب أن يُبطئ الـ search response
     */
    public function execute(
        int    $projectId,
        string $keyword,
        string $language
    ): void {
        try {
            // تجاهل الكلمات القصيرة جداً
            if (mb_strlen(trim($keyword), 'UTF-8') < 2) {
                return;
            }

            $this->repository->upsertFromSearch($projectId, $keyword, $language);

        } catch (\Throwable $e) {
            // لا نوقف البحث إذا فشل sync الـ suggestions
            Log::warning('SyncSuggestionAction: failed to sync', [
                'project_id' => $projectId,
                'keyword'    => $keyword,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}