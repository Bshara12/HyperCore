<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Repositories\Interfaces\PopularSearchRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecomputePopularSearchesAction
{
    public function __construct(
        private PopularSearchRepositoryInterface $repository,
    ) {}

    /**
     * @return array{project_id: int, language: string, stats: array}[]
     */
    public function execute(?int $projectId = null): array
    {
        $results = [];

        // جلب كل combinations الـ project+language الموجودة في الـ logs
        $combinations = DB::table('user_search_logs')
            ->select('project_id', 'language')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->distinct()
            ->get();

        foreach ($combinations as $combo) {
            try {
                $stats = $this->repository->recompute(
                    (int) $combo->project_id,
                    $combo->language
                );

                // مسح الـ cache بعد الـ recompute
                $this->invalidateCacheForProject(
                    (int) $combo->project_id,
                    $combo->language
                );

                $results[] = [
                    'project_id' => $combo->project_id,
                    'language' => $combo->language,
                    'stats' => $stats,
                ];

                Log::info('PopularSearches recomputed', [
                    'project_id' => $combo->project_id,
                    'language' => $combo->language,
                    'stats' => $stats,
                ]);

            } catch (\Throwable $e) {
                Log::error('PopularSearches recompute failed', [
                    'project_id' => $combo->project_id,
                    'language' => $combo->language,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * مسح كل cache keys المتعلقة بـ project + language معين
     *
     * نستخدم Cache Tags إذا كان الـ driver يدعمها (Redis/Memcached)
     * وإلا نمسح بـ pattern
     */
    private function invalidateCacheForProject(int $projectId, string $language): void
    {
        $windows = ['24h', '7d', '30d', 'all'];
        $types = ['trending', 'popular', 'both'];
        $limits = [5, 10, 15, 20];

        foreach ($windows as $window) {
            foreach ($types as $type) {
                foreach ($limits as $limit) {
                    $key = sprintf(
                        'popular_searches:%d:%s:%s:%s:%d',
                        $projectId, $language, $window, $type, $limit
                    );
                    Cache::forget($key);
                }
            }
        }
    }
}
