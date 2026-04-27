<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\PopularSearchItemDTO;
use App\Domains\Search\DTOs\PopularSearchQueryDTO;
use App\Domains\Search\DTOs\PopularSearchResultDTO;
use App\Models\PopularSearch;
use App\Domains\Search\Repositories\Interfaces\PopularSearchRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class GetPopularSearchesAction
{
    private const CACHE_TTL_SECONDS = 1800;

    public function __construct(
        private PopularSearchRepositoryInterface $repository,
    ) {}

    // ─────────────────────────────────────────────────────────────────

    public function execute(PopularSearchQueryDTO $dto): PopularSearchResultDTO
    {
        $startTime = microtime(true);
        $cacheKey  = $this->buildCacheKey($dto);

        // ─── Cache Check ──────────────────────────────────────────────
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return new PopularSearchResultDTO(
                trending:         $cached['trending'],
                popular:          $cached['popular'],
                window:           $dto->window,
                actualWindowUsed: $cached['actual_window_used'],
                fallbackApplied:  $cached['fallback_applied'],
                source:           'cache',
                tookMs:           (microtime(true) - $startTime) * 1000,
            );
        }

        // ─── جلب من DB مع Fallback ────────────────────────────────────
        $trendingResult = ($dto->type === 'popular')
            ? ['rows' => [], 'window_used' => $dto->window, 'fallback_applied' => false]
            : $this->repository->getTrending($dto);

        $popularResult = ($dto->type === 'trending')
            ? ['rows' => [], 'window_used' => $dto->window, 'fallback_applied' => false]
            : $this->repository->getPopular($dto);

        // ─── تحديد الـ window الفعلي المستخدم ─────────────────────────
        // إذا كلاهما طبّق fallback → نأخذ الأبعد
        $actualWindowUsed = $this->resolveActualWindow(
            $trendingResult['window_used'],
            $popularResult['window_used'],
        );

        $fallbackApplied = $trendingResult['fallback_applied']
                        || $popularResult['fallback_applied'];

        // ─── تحويل إلى DTOs ──────────────────────────────────────────
        $trending = $this->mapRows(
            $trendingResult['rows'],
            $trendingResult['window_used']
        );

        $popular = $this->mapRows(
            $popularResult['rows'],
            $popularResult['window_used']
        );

        // ─── Cache ───────────────────────────────────────────────────
        $cachePayload = [
            'trending'          => $trending,
            'popular'           => $popular,
            'actual_window_used'=> $actualWindowUsed,
            'fallback_applied'  => $fallbackApplied,
        ];

        Cache::put($cacheKey, $cachePayload, self::CACHE_TTL_SECONDS);

        return new PopularSearchResultDTO(
            trending:         $trending,
            popular:          $popular,
            window:           $dto->window,
            actualWindowUsed: $actualWindowUsed,
            fallbackApplied:  $fallbackApplied,
            source:           'db',
            tookMs:           (microtime(true) - $startTime) * 1000,
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  object[] $rows
     * @return PopularSearchItemDTO[]
     */
    private function mapRows(array $rows, string $windowUsed): array
    {
        return array_map(function (object $row) use ($windowUsed) {
            $trend = PopularSearch::detectTrend(
                count24h: (int) ($row->count_24h ?? 0),
                count7d:  (int) ($row->count_7d  ?? 0),
            );

            return new PopularSearchItemDTO(
                keyword:    $row->keyword,
                count:      (int) $row->count,
                score:      (float) $row->score,
                trend:      $trend,
                windowUsed: $windowUsed,
            );
        }, $rows);
    }

    /**
     * إذا trending استخدم '7d' وpopular استخدم '30d'
     * نُرجع '30d' لأنه الأوسع (الأبعد عن المطلوب)
     */
    private function resolveActualWindow(string $trendingWindow, string $popularWindow): string
    {
        $priority = ['24h' => 1, '7d' => 2, '30d' => 3, 'all' => 4];

        $trendingPriority = $priority[$trendingWindow] ?? 4;
        $popularPriority  = $priority[$popularWindow]  ?? 4;

        return $trendingPriority >= $popularPriority
            ? $trendingWindow
            : $popularWindow;
    }

    private function buildCacheKey(PopularSearchQueryDTO $dto): string
    {
        return sprintf(
            'popular_searches:%d:%s:%s:%s:%d',
            $dto->projectId,
            $dto->language,
            $dto->window,
            $dto->type,
            $dto->limit
        );
    }
}