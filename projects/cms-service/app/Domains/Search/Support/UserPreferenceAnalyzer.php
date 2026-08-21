<?php

namespace App\Domains\Search\Support;

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserPreferenceAnalyzer
{
    private const MIN_CLICKS_FOR_SIGNAL = 2;
    private const SATURATION_K = 3.0;
    private const MIN_TERM_SIGNAL = 2;
    private const TERM_SATURATION_K = 2.0;
    private const VOCAB_CAP = 20;
    private const MAX_CLICKS_ANALYZED = 100;
    private const CACHE_TTL_MINUTES = 15;

    /**
     * نسخة مفتاح الـ cache.
     *
     * تُرفَع عند أي تغيير في شكل الرموز المُخزَّنة داخل الـ DTO، وإلا
     * بقيت التفضيلات المُخزَّنة بالشكل القديم ("آيفون") لا تُطابق رموز
     * الـ ranker الجديدة ("ايفون") إلى أن تنتهي مدة الـ TTL.
     */
    private const CACHE_VERSION = 'v2';
    private const ANALYSIS_DAYS = 30;

    private const DOMAIN_NEUTRAL_WORDS = [
        'new' => true, 'best' => true, 'sale' => true, 'price' => true,
        'free' => true, 'latest' => true, 'top' => true, 'cheap' => true,
        'deal' => true, 'offer' => true, 'discount' => true, 'buy' => true,
        'shop' => true, 'shipping' => true, 'delivery' => true,
        'affordable' => true, 'compare' => true, 'guide' => true,
        'review' => true, 'tutorial' => true,
    ];

    public function __construct(
        private UserBehaviorRepositoryInterface $repository,
        private KeywordTokenizer $tokenizer,
    ) {}

    public function analyzeForUser(int $projectId, int $userId): UserPreferenceDTO
    {
        $cacheKey = self::userCacheKey($projectId, $userId);

        return $this->resolveFromCache(
            $cacheKey,
            function () use ($projectId, $userId) {
                $clickCounts = $this->repository->getClickCountsByDataType(
                    $projectId, $userId, self::ANALYSIS_DAYS
                );
                $indexedTexts = $this->repository->getClickedEntryTexts(
                    $projectId, $userId, self::ANALYSIS_DAYS, self::MAX_CLICKS_ANALYZED
                );

                return $this->buildPreference($clickCounts, $indexedTexts);
            }
        );
    }

    public function analyzeForSession(int $projectId, string $sessionId): UserPreferenceDTO
    {
        $cacheKey = self::sessionCacheKey($projectId, $sessionId);

        return $this->resolveFromCache(
            $cacheKey,
            function () use ($projectId, $sessionId) {
                $clickCounts = $this->repository->getClickCountsByDataTypeForSession(
                    $projectId, $sessionId, self::ANALYSIS_DAYS
                );
                $indexedTexts = $this->repository->getClickedEntryTextsForSession(
                    $projectId, $sessionId, self::ANALYSIS_DAYS, self::MAX_CLICKS_ANALYZED
                );

                return $this->buildPreference($clickCounts, $indexedTexts);
            }
        );
    }

    public function analyze(
        int $projectId,
        ?int $userId,
        ?string $sessionId
    ): UserPreferenceDTO {
        Log::debug('UserPreferenceAnalyzer::analyze called', [
            'project_id' => $projectId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'path' => $userId !== null ? 'user' : ($sessionId !== null ? 'session' : 'no_history'),
        ]);

        if ($userId !== null) {
            return $this->analyzeForUser($projectId, $userId);
        }

        if ($sessionId !== null) {
            return $this->analyzeForSession($projectId, $sessionId);
        }

        return UserPreferenceDTO::noHistory();
    }

    public function invalidateCache(int $projectId, ?int $userId, ?string $sessionId = null): void
    {
        if ($userId !== null) {
            Cache::forget(self::userCacheKey($projectId, $userId));
        }

        if ($sessionId !== null) {
            Cache::forget(self::sessionCacheKey($projectId, $sessionId));
        }
    }

    private static function userCacheKey(int $projectId, int $userId): string
    {
        return 'user_preference:'.self::CACHE_VERSION.":{$projectId}:{$userId}";
    }

    private static function sessionCacheKey(int $projectId, string $sessionId): string
    {
        return 'session_preference:'.self::CACHE_VERSION.":{$projectId}:{$sessionId}";
    }

    private function resolveFromCache(string $cacheKey, \Closure $builder): UserPreferenceDTO
    {
        $cached = Cache::get($cacheKey);

        if ($cached instanceof UserPreferenceDTO) {
            return $cached;
        }

        if ($cached !== null) {
            Log::warning('UserPreferenceAnalyzer: unexpected cache value type, rebuilding from source', [
                'cache_key' => $cacheKey,
                'actual_type' => get_debug_type($cached),
            ]);
        }

        $fresh = $builder();

        Cache::put($cacheKey, $fresh, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $fresh;
    }

    private function buildPreference(array $clickCounts, array $indexedTexts): UserPreferenceDTO
    {
        $totalClicks = array_sum($clickCounts);

        $dataTypeAffinities = $this->buildDataTypeAffinities($clickCounts);
        $termAffinities = $this->buildTermAffinities($indexedTexts);

        if (empty($dataTypeAffinities) && empty($termAffinities)) {
            return UserPreferenceDTO::noHistory();
        }

        return new UserPreferenceDTO(
            affinities: $dataTypeAffinities,
            termAffinities: $termAffinities,
            totalClicks: $totalClicks,
            hasHistory: true,
        );
    }

    private function buildDataTypeAffinities(array $clickCounts): array
    {
        if (empty($clickCounts)) {
            return [];
        }

        $affinities = [];

        foreach ($clickCounts as $dataTypeId => $count) {
            $count = (int) $count;

            if ($count < self::MIN_CLICKS_FOR_SIGNAL) {
                continue;
            }

            $affinities[(int) $dataTypeId] = round(
                $count / ($count + self::SATURATION_K),
                4
            );
        }

        return $affinities;
    }

    private function buildTermAffinities(array $indexedTexts): array
    {
        if (empty($indexedTexts)) {
            return [];
        }

        $termCounts = [];

        foreach ($indexedTexts as $text) {
            $tokens = $this->tokenizer->tokenize($text);

            foreach ($tokens as $token) {
                if (isset(self::DOMAIN_NEUTRAL_WORDS[$token])) {
                    continue;
                }

                $termCounts[$token] = ($termCounts[$token] ?? 0) + 1;
            }
        }

        if (empty($termCounts)) {
            return [];
        }

        $termAffinities = [];

        foreach ($termCounts as $term => $count) {
            if ($count < self::MIN_TERM_SIGNAL) {
                continue;
            }

            $termAffinities[$term] = round(
                $count / ($count + self::TERM_SATURATION_K),
                4
            );
        }

        if (empty($termAffinities)) {
            return [];
        }

        arsort($termAffinities);

        return array_slice($termAffinities, 0, self::VOCAB_CAP, true);
    }
}