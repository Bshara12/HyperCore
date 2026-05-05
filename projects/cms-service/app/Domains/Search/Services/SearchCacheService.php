<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchCacheService
{
    /*
     * TTL الافتراضي = 10 دقائق
     * Hot cache = 20 ثانية (نفس الـ query في وقت قصير)
     * Trending cache = 30 دقيقة (queries شائعة تتغير ببطء)
     */
    private const TTL_DEFAULT = 600;   // 10 دقائق

    private const TTL_HOT = 20;    // 20 ثانية للـ burst traffic

    private const TTL_TRENDING = 1800;  // 30 دقيقة للـ trending

    /*
     * الـ cache tag الرئيسي
     * يُستخدم للـ invalidation الجماعي عند الـ reindex
     */
    private const CACHE_TAG = 'search_results';

    // ─────────────────────────────────────────────────────────────────

    /**
     * جلب نتائج من الـ cache أو تنفيذ الـ callback وحفظ النتيجة
     *
     * @param  callable(): array  $callback
     */
    public function remember(
        SearchQueryDTO $dto,
        UserPreferenceDTO $preference,
        string $intent,
        callable $callback
    ): array {
        $cacheKey = $this->buildKey($dto, $preference, $intent);
        $hotKey = $this->buildHotKey($dto);

        // ─── 1. Hot Cache Check (20 ثانية) ────────────────────────────
        $hot = $this->getFromCache($hotKey);
        if ($hot !== null) {
            Log::debug('SearchCache: hot cache hit', ['key' => $hotKey]);

            return array_merge($hot, ['cache_source' => 'hot']);
        }

        // ─── 2. Main Cache Check ──────────────────────────────────────
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            // نُجدد الـ hot cache من الـ main cache
            $this->putToCache($hotKey, $cached, self::TTL_HOT);

            Log::debug('SearchCache: main cache hit', ['key' => $cacheKey]);

            return array_merge($cached, ['cache_source' => 'main']);
        }

        // ─── 3. تنفيذ البحث الفعلي ────────────────────────────────────
        $result = $callback();

        if (empty($result['items']) && $result['total'] === 0) {
            // لا نُخزّن النتائج الفارغة (قد تتغير بعد reindex)
            return array_merge($result, ['cache_source' => 'db_empty']);
        }

        // ─── 4. اختر الـ TTL المناسب ─────────────────────────────────
        $ttl = $this->resolveTTL($dto->keyword, $result);

        $this->putToCache($cacheKey, $result, $ttl);
        $this->putToCache($hotKey, $result, self::TTL_HOT);

        Log::debug('SearchCache: cached result', [
            'key' => $cacheKey,
            'ttl' => $ttl,
            'total' => $result['total'],
        ]);

        return array_merge($result, ['cache_source' => 'db']);
    }

    // ─────────────────────────────────────────────────────────────────
    // Cache Invalidation
    // ─────────────────────────────────────────────────────────────────

    /**
     * مسح كل cache لمشروع معين
     * يُستدعى عند الـ reindex
     */
    public function invalidateProject(int $projectId): void
    {
        try {
            // إذا كان الـ driver يدعم tags (Redis/Memcached)
            if ($this->supportsTagging()) {
                Cache::tags([self::CACHE_TAG, "project:{$projectId}"])->flush();
                Log::info('SearchCache: invalidated by tag', ['project_id' => $projectId]);

                return;
            }

            // Fallback: مسح بالـ prefix
            $this->flushByPrefix("search:{$projectId}:");
            Log::info('SearchCache: invalidated by prefix', ['project_id' => $projectId]);

        } catch (\Throwable $e) {
            Log::warning('SearchCache: invalidation failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * مسح cache لـ entry محدد عند نشره
     * يُستدعى من EntryPublishedEvent
     */
    public function invalidateEntry(int $projectId, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            $pattern = "search:{$projectId}:*:".md5(mb_strtolower(trim($keyword))).':*';
            $this->flushByPrefix("search:{$projectId}:");
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Cache Warming
    // ─────────────────────────────────────────────────────────────────

    /**
     * تسخين الـ cache للـ queries الشائعة
     * يُستدعى بعد الـ reindex مباشرة
     */
    public function warmTrendingQueries(
        int $projectId,
        array $trendingKeywords,
        callable $searchCallback
    ): void {
        foreach (array_slice($trendingKeywords, 0, 20) as $keyword) {
            try {
                $cacheKey = "search:{$projectId}:en:".md5(mb_strtolower($keyword)).':1:general:none';

                if (! $this->getFromCache($cacheKey)) {
                    $result = $searchCallback($keyword);

                    if (! empty($result['items'])) {
                        $this->putToCache($cacheKey, $result, self::TTL_TRENDING);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('SearchCache: warming failed for keyword', [
                    'keyword' => $keyword,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SearchCache: warmed trending queries', [
            'project_id' => $projectId,
            'count' => count($trendingKeywords),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Key Building
    // ─────────────────────────────────────────────────────────────────

    /**
     * بناء الـ cache key
     *
     * الصيغة:
     *   search:{projectId}:{lang}:{queryHash}:{page}:{perPage}:{intent}:{prefType}
     *
     * queryHash = md5(lowercase query) لتجنب مشاكل الأحرف الخاصة
     * prefType = نوع التفضيل وليس الـ userId (لأن users مختلفين بنفس التفضيل يأخذون نفس النتائج)
     */
    public function buildKey(
        SearchQueryDTO $dto,
        UserPreferenceDTO $preference,
        string $intent
    ): string {
        $queryHash = md5(mb_strtolower(trim($dto->keyword), 'UTF-8'));
        $prefType = $preference->hasHistory ? $preference->preferredType : 'none';
        $dataTypeKey = $dto->dataTypeSlug ?? 'all';

        return sprintf(
            'search:%d:%s:%s:%d:%d:%s:%s:%s',
            $dto->projectId,
            $dto->language,
            $queryHash,
            $dto->page,
            $dto->perPage,
            $intent,
            $prefType,
            $dataTypeKey
        );
    }

    /**
     * Hot key: أقصر وأسرع للـ burst traffic
     * لا يشمل الـ intent أو الـ preference (نفس النتائج لكل users في 20 ثانية)
     */
    private function buildHotKey(SearchQueryDTO $dto): string
    {
        $queryHash = md5(mb_strtolower(trim($dto->keyword), 'UTF-8'));

        return sprintf(
            'search_hot:%d:%s:%s:%d',
            $dto->projectId,
            $dto->language,
            $queryHash,
            $dto->page
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // TTL Resolution
    // ─────────────────────────────────────────────────────────────────

    /**
     * اختيار الـ TTL بناءً على طبيعة الـ query والنتائج
     *
     * Trending query (نتائج كثيرة) → TTL أطول
     * Query عادي → TTL افتراضي
     */
    private function resolveTTL(string $keyword, array $result): int
    {
        // queries بنتائج كثيرة = trending = cache أطول
        if ($result['total'] >= 50) {
            return self::TTL_TRENDING;
        }

        // كلمة واحدة قصيرة = trending محتمل
        $wordCount = str_word_count($keyword);
        if ($wordCount === 1 && mb_strlen($keyword) >= 4) {
            return self::TTL_TRENDING;
        }

        return self::TTL_DEFAULT;
    }

    // ─────────────────────────────────────────────────────────────────
    // Cache Driver Helpers
    // ─────────────────────────────────────────────────────────────────

    private function getFromCache(string $key): ?array
    {
        try {
            $value = $this->supportsTagging()
                ? Cache::tags(self::CACHE_TAG)->get($key)
                : Cache::get($key);

            return is_array($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function putToCache(string $key, array $value, int $ttl): void
    {
        try {
            if ($this->supportsTagging()) {
                Cache::tags(self::CACHE_TAG)->put($key, $value, $ttl);
            } else {
                Cache::put($key, $value, $ttl);
            }
        } catch (\Throwable $e) {
            Log::warning('SearchCache: put failed', ['error' => $e->getMessage()]);
        }
    }

    private function flushByPrefix(string $prefix): void
    {
        // هذا يعمل مع Redis فقط
        try {
            $redis = Cache::getRedis();
            $keys = $redis->keys(config('cache.prefix').$prefix.'*');

            foreach ($keys as $key) {
                $redis->del($key);
            }
        } catch (\Throwable $e) {
            Log::warning('SearchCache: prefix flush failed', ['error' => $e->getMessage()]);
        }
    }

    private function supportsTagging(): bool
    {
        return in_array(
            config('cache.default'),
            ['redis', 'memcached'],
            true
        );
    }
}
