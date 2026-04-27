<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\SuggestionItemDTO;
use App\Domains\Search\DTOs\SuggestionQueryDTO;
use App\Domains\Search\DTOs\SuggestionResultDTO;
use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class GetSuggestionsAction
{
    /*
     * TTL قصير جداً للـ cache
     * الـ suggestions تتغير مع الوقت لكن ليس بشكل لحظي
     *
     * 5 دقائق = توازن بين الأداء والحداثة
     */
    private const CACHE_TTL_SECONDS = 300;

    /*
     * الحد الأدنى لطول الـ prefix للبحث
     * أقل من 2 حرف = نتائج كثيرة جداً وغير مفيدة
     */
    private const MIN_PREFIX_LENGTH = 2;

    public function __construct(
        private SuggestionRepositoryInterface $repository,
    ) {}

    // ─────────────────────────────────────────────────────────────────

    public function execute(SuggestionQueryDTO $dto): SuggestionResultDTO
    {
        $startTime = microtime(true);

        // ─── تحقق من الحد الأدنى للطول ──────────────────────────────
        if (mb_strlen(trim($dto->prefix), 'UTF-8') < self::MIN_PREFIX_LENGTH) {
            return new SuggestionResultDTO(
                prefix:  $dto->prefix,
                items:   [],
                source:  'skipped',
                tookMs:  (microtime(true) - $startTime) * 1000,
            );
        }

        // ─── Cache key فريد لكل combination ─────────────────────────
        $cacheKey = $this->buildCacheKey($dto);

        // ─── جرّب الـ cache أولاً ─────────────────────────────────────
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return new SuggestionResultDTO(
                prefix:  $dto->prefix,
                items:   $cached,
                source:  'cache',
                tookMs:  (microtime(true) - $startTime) * 1000,
            );
        }

        // ─── جلب من DB ───────────────────────────────────────────────
        $rows  = $this->repository->findByPrefix($dto);
        $items = $this->mapToItems($rows, $dto->prefix);

        // ─── حفظ في Cache ────────────────────────────────────────────
        Cache::put($cacheKey, $items, self::CACHE_TTL_SECONDS);

        return new SuggestionResultDTO(
            prefix:  $dto->prefix,
            items:   $items,
            source:  'db',
            tookMs:  (microtime(true) - $startTime) * 1000,
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تحويل rows DB إلى DTOs مع إضافة الـ highlighting
     */
    private function mapToItems(array $rows, string $prefix): array
    {
        return array_map(function (object $row) use ($prefix) {
            return new SuggestionItemDTO(
                keyword:     $row->keyword,
                searchCount: (int) $row->search_count,
                score:       (float) $row->score,
                highlight:   $this->highlightPrefix($row->keyword, $prefix),
            );
        }, $rows);
    }

    /**
     * تمييز الـ prefix في الـ keyword للـ UI
     *
     * مثال:
     *   keyword = "laravel tutorial"
     *   prefix  = "lar"
     *   output  = "**lar**avel tutorial"
     */
    private function highlightPrefix(string $keyword, string $prefix): string
    {
        $prefixLen = mb_strlen($prefix, 'UTF-8');

        // تحقق أن الـ keyword يبدأ بالـ prefix (case insensitive)
        if (mb_stripos($keyword, $prefix, 0, 'UTF-8') !== 0) {
            return $keyword;
        }

        $highlighted = mb_substr($keyword, 0, $prefixLen, 'UTF-8');
        $rest        = mb_substr($keyword, $prefixLen, null, 'UTF-8');

        return "**{$highlighted}**{$rest}";
    }

    /**
     * بناء cache key فريد
     *
     * نُطبِّع الـ prefix حتى "LAR" و "lar" و "Lar" يعطون نفس cache key
     */
    private function buildCacheKey(SuggestionQueryDTO $dto): string
    {
        $normalizedPrefix = mb_strtolower(trim($dto->prefix), 'UTF-8');

        return sprintf(
            'suggestions:%d:%s:%s',
            $dto->projectId,
            $dto->language,
            md5($normalizedPrefix)  // md5 لتجنب مشاكل الأحرف الخاصة في cache key
        );
    }
}