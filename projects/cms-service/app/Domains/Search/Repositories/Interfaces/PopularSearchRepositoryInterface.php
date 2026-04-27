<?php

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\PopularSearchQueryDTO;

interface PopularSearchRepositoryInterface
{
    /**
     * جلب trending مع smart fallback
     *
     * @return array{rows: object[], window_used: string, fallback_applied: bool}
     */
    public function getTrending(PopularSearchQueryDTO $dto): array;

    /**
     * جلب popular مع smart fallback
     *
     * @return array{rows: object[], window_used: string, fallback_applied: bool}
     */
    public function getPopular(PopularSearchQueryDTO $dto): array;

    /**
     * إعادة حساب الـ popular_searches من user_search_logs
     *
     * @return array{processed: int, upserted: int, duration_ms: float}
     */
    public function recompute(int $projectId, string $language): array;
}