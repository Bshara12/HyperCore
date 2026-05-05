<?php

namespace App\Domains\Search\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynonymExpander
{
    /*
     * إعدادات الـ Cache والحدود
     */
    private const CACHE_TTL_SECONDS = 3600;  // ساعة واحدة

    private const MAX_SYNONYMS_PER_WORD = 3;     // أقصى 3 مرادفات لكل كلمة

    private const MIN_CONFIDENCE = 0.5;   // confidence أدنى مقبول

    // ─────────────────────────────────────────────────────────────────

    /**
     * توسيع الـ tokens بإضافة مرادفاتها المعتمدة
     *
     * @param  string[]  $tokens  الكلمات بعد الـ tokenization
     * @return array{
     *   expanded: string[],
     *   groups:   array<string, string[]>,
     *   hadExpansion: bool
     * }
     *
     * مثال:
     *   tokens:   ["iphone", "cost"]
     *   synonyms: cost → [price, fee]
     *   returned: [
     *     'expanded'     => ["iphone", "cost", "price", "fee"],
     *     'groups'       => ["iphone" => ["iphone"], "cost" => ["cost","price","fee"]],
     *     'hadExpansion' => true,
     *   ]
     */
    public function expand(
        array $tokens,
        int $projectId,
        string $language
    ): array {
        if (empty($tokens)) {
            return [
                'expanded' => [],
                'groups' => [],
                'hadExpansion' => false,
            ];
        }

        // ─── جلب كل المرادفات للمشروع (من cache أو DB) ───────────────
        $synonymMap = $this->loadSynonymMap($projectId, $language);

        $expandedAll = [];
        $groups = [];
        $hadExpansion = false;

        foreach ($tokens as $token) {
            $token = mb_strtolower(trim($token), 'UTF-8');
            $synonyms = $synonymMap[$token] ?? [];

            // بناء مجموعة الكلمة: [أصلية + مرادفاتها]
            $group = [$token];

            foreach ($synonyms as $synonym) {
                // تجنب التكرار مع tokens أخرى
                if (! in_array($synonym, $tokens, true) && ! in_array($synonym, $group, true)) {
                    $group[] = $synonym;
                    $hadExpansion = true;
                }
            }

            $groups[$token] = $group;

            // إضافة الكلمة الأصلية والمرادفات للـ flat array
            foreach ($group as $word) {
                if (! in_array($word, $expandedAll, true)) {
                    $expandedAll[] = $word;
                }
            }
        }

        return [
            'expanded' => $expandedAll,
            'groups' => $groups,
            'hadExpansion' => $hadExpansion,
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * جلب synonym map كاملة للمشروع واللغة
     *
     * الشكل: ['cost' => ['price', 'fee'], 'mobile' => ['phone', 'cellphone'], ...]
     *
     * يجلب من DB مرة واحدة ويُخزَّن في cache
     * بدل query لكل token على حدة
     *
     * @return array<string, string[]>
     */
    public function loadSynonymMap(int $projectId, string $language): array
    {
        $cacheKey = "synonym_map:{$projectId}:{$language}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            function () use ($projectId, $language) {
                return $this->buildSynonymMapFromDB($projectId, $language);
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * بناء الـ synonym map من DB
     *
     * كل سجل يحتوي word_a ↔ word_b
     * نبني map في الاتجاهين معاً
     *
     * مثال DB:
     *   | word_a  | word_b | confidence |
     *   | cost    | price  | 0.85       |
     *   | mobile  | phone  | 0.72       |
     *
     * Map الناتجة:
     *   cost:   [price]
     *   price:  [cost]
     *   mobile: [phone]
     *   phone:  [mobile]
     *
     * @return array<string, string[]>
     */
    private function buildSynonymMapFromDB(int $projectId, string $language): array
    {
        /*
         * query واحد يجلب كل المرادفات المعتمدة
         * مرتبة حسب confidence تنازلياً
         * حتى عند الـ slice نأخذ الأقوى أولاً
         */
        $rows = DB::table('synonym_suggestions')
            ->select('word_a', 'word_b', 'confidence_score')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->where('status', 'approved')
            ->where('confidence_score', '>=', self::MIN_CONFIDENCE)
            ->orderByDesc('confidence_score')
            ->get();

        if ($rows->isEmpty()) {
            Log::info('SynonymExpander: no approved synonyms found', [
                'project_id' => $projectId,
                'language' => $language,
            ]);

            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $wordA = mb_strtolower(trim($row->word_a), 'UTF-8');
            $wordB = mb_strtolower(trim($row->word_b), 'UTF-8');

            // ─── الاتجاه الأول: A → B ─────────────────────────────────
            if (! isset($map[$wordA])) {
                $map[$wordA] = [];
            }

            if (
                ! in_array($wordB, $map[$wordA], true) &&
                count($map[$wordA]) < self::MAX_SYNONYMS_PER_WORD
            ) {
                $map[$wordA][] = $wordB;
            }

            // ─── الاتجاه الثاني: B → A ────────────────────────────────
            if (! isset($map[$wordB])) {
                $map[$wordB] = [];
            }

            if (
                ! in_array($wordA, $map[$wordB], true) &&
                count($map[$wordB]) < self::MAX_SYNONYMS_PER_WORD
            ) {
                $map[$wordB][] = $wordA;
            }
        }

        Log::info('SynonymExpander: synonym map built', [
            'project_id' => $projectId,
            'language' => $language,
            'unique_words' => count($map),
            'total_rows' => $rows->count(),
        ]);

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * مسح الـ cache عند اعتماد مرادف جديد
     * يُستدعى من SynonymAnalysisService::reviewSuggestion()
     */
    public function invalidateCache(int $projectId, string $language): void
    {
        Cache::forget("synonym_map:{$projectId}:{$language}");

        Log::info('SynonymExpander: cache invalidated', [
            'project_id' => $projectId,
            'language' => $language,
        ]);
    }

    /**
     * مسح cache كل اللغات لمشروع معين
     */
    public function invalidateCacheForProject(int $projectId): void
    {
        $languages = ['en', 'ar', 'fr', 'de', 'es'];

        foreach ($languages as $lang) {
            Cache::forget("synonym_map:{$projectId}:{$lang}");
        }
    }
}
