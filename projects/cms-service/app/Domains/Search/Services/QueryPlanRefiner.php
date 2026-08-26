<?php

declare(strict_types=1);

namespace App\Domains\Search\Services;

use App\Domains\Search\Services\AI\QueryInterpreterInterface;
use App\Domains\Search\Support\Query\QueryPlan;
use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QueryPlanRefiner — الاحتياطي الذكي، محكوماً.
 *
 * ─── متى يُستدعى ────────────────────────────────────────────────────
 *
 * بعد فشل المسار المحلّي في إيجاد أي نتيجة. لا قبله ولا بالتوازي معه.
 *
 * الإصدار السابق كان يستدعي النموذج لكل استعلام عربي تقريباً، لأن
 * المُطبِّع كان يضع isNaturalLanguage=true لمجرّد وجود حرف عربي. أي أن
 * البحث العربي كله كان يمرّ عبر الشبكة: زمن استجابة غير قابل للتنبؤ،
 * وكلفة لكل طلب، ونتائج لا تُعاد في الاختبار — وعطل المزوّد يعطّل
 * البحث العربي بأكمله.
 *
 * ─── الضمانات الأربع ────────────────────────────────────────────────
 *
 *  1. مخرَج مقولب. النموذج يملأ QueryPlan ولا يكتب نصّ استعلام. كل
 *     مصطلح يمرّ بالتطبيع والتقسيم كأي مدخل مستخدم، فيستحيل أن يصل
 *     محرف نحوي إلى BOOLEAN MODE. الإصدار السابق كان يلصق مخرَج
 *     النموذج مباشرةً في نحو الاستعلام.
 *
 *  2. ذاكرة دائمة. كل استعلام مميّز يكلّف استدعاءً واحداً على الأكثر
 *     خلال مدّة الصلاحية. الاستعلامات الشائعة — وهي الأغلبية الساحقة
 *     من حركة أي محرك بحث — تُخدَم من الذاكرة.
 *
 *  3. قاطع دارة. بعد إخفاقات متتالية يتوقّف الاتصال لمدّة تهدئة.
 *     بدونه يتحوّل عطل المزوّد إلى إضافة ثوانٍ على كل بحث فاشل في
 *     الموقع، وهو أسوأ من غياب الميزة.
 *
 *  4. مهلة صارمة. الفشل داخل المهلة يعيد نتيجة المسار المحلّي بدل
 *     أن يعلّق الطلب.
 */
final class QueryPlanRefiner
{
    private const CIRCUIT_KEY = 'search:ai:circuit';

    public function __construct(
        private readonly QueryInterpreterInterface $interpreter,
    ) {}

    /**
     * محاولة تحسين خطة أخفقت.
     *
     * تعيد null حين يتعذّر التحسين — والمتصل يُبقي خطته الأصلية.
     */
    public function refine(QueryPlan $plan, int $projectId, string $language): ?QueryPlan
    {
        if (! (bool) config('search.ai.enabled', false)) {
            return null;
        }

        $cached = $this->fromCache($plan, $projectId, $language);

        if ($cached !== null) {
            return $cached;
        }

        if ($this->circuitIsOpen()) {
            Log::debug('QueryPlanRefiner: circuit open, skipping provider');

            return null;
        }

        $interpretation = $this->callProvider($plan, $language);

        if ($interpretation === null) {
            return null;
        }

        $refined = $this->toPlan($plan, $interpretation);

        if ($refined === null) {
            return null;
        }

        $this->remember($plan, $projectId, $language, $refined, $interpretation);

        return $refined;
    }

    // ─────────────────────────────────────────────────────────────────

    private function callProvider(QueryPlan $plan, string $language): ?array
    {
        try {
            $result = $this->interpreter->interpret($plan->original, $language);
        } catch (\Throwable $e) {
            $this->recordFailure();

            Log::warning('QueryPlanRefiner: provider failed', [
                'query' => $plan->original,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_array($result)) {
            $this->recordFailure();

            return null;
        }

        $this->recordSuccess();

        return $result;
    }

    /**
     * تحويل مخرَج النموذج إلى خطة.
     *
     * ─── لماذا تمرّ مصطلحات النموذج بالتقسيم ────────────────────────
     *
     * لأن ما يعود من النموذج نصّ حرّ لا وحدات. قد يعيد "iPhone 15 Pro"
     * سلسلةً واحدة، أو يضيف ترقيماً، أو يخلط اللغات. تمريرها بنفس
     * مسار مدخل المستخدم يضمن أنها تصل إلى الفهرس بالصورة نفسها التي
     * فُهرس بها المحتوى — وأنها لا تحمل محرفاً نحوياً.
     *
     * @param  array<string, mixed>  $interpretation
     */
    private function toPlan(QueryPlan $original, array $interpretation): ?QueryPlan
    {
        $confidence = (float) ($interpretation['confidence'] ?? 0.0);

        if ($confidence < 0.20) {
            Log::info('QueryPlanRefiner: interpretation confidence too low', [
                'query' => $original->original,
                'confidence' => $confidence,
            ]);

            return null;
        }

        $terms = $this->tokenizeAll($interpretation['include'] ?? []);
        $mustNot = $this->tokenizeAll($interpretation['exclude'] ?? []);

        if ($terms === []) {
            return null;
        }

        /*
         | تجاهل التفسير الذي لا يضيف شيئاً.
         |
         | نموذج يعيد الاستعلام كما هو لم يفهم شيئاً، والبحث بمصطلحاته
         | سيخفق تماماً كما أخفق المسار المحلّي — فنوفّر الرحلة الثانية
         | إلى قاعدة البيانات.
         */
        if ($terms === $original->terms) {
            return null;
        }

        return new QueryPlan(
            original: $original->original,
            folded: $original->folded,
            terms: array_slice($terms, 0, (int) config('search.understanding.max_terms', 12)),
            phrases: count($terms) >= 2 ? [implode(' ', $terms)] : [],
            mustNot: array_values(array_unique([...$original->mustNot, ...$mustNot])),
            expansions: [],

            /*
             | الشروط البنيوية تبقى من المحلّل المحلّي.
             |
             | استخراج السنة والسعر حتمي ومُختبَر ولا يحتاج نموذجاً؛
             | والسماح للنموذج بتوليد شروط تُقصي نتائج يعني إعطاءه
             | سلطة إخفاء المحتوى بناءً على استنتاج غير قابل للتحقّق.
             */
            filters: $original->filters,

            scripts: $original->scripts,
            intent: $original->intent,
            needsNgram: $original->needsNgram,
            isNaturalLanguage: $original->isNaturalLanguage,
            dataTypeSlug: $original->dataTypeSlug,
            source: 'ai',
        );
    }

    /**
     * @return string[]
     */
    private function tokenizeAll(mixed $values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (! is_array($values)) {
            return [];
        }

        $terms = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            foreach (Segmenter::tokenize(TextFolder::fold((string) $value)) as $token) {
                $terms[$token] = true;
            }
        }

        return array_keys($terms);
    }

    // ─── الذاكرة الدائمة ─────────────────────────────────────────────

    private function fromCache(QueryPlan $plan, int $projectId, string $language): ?QueryPlan
    {
        try {
            $row = DB::table('search_query_plans')
                ->where('project_id', $projectId)
                ->where('language', $language)
                ->where('query_hash', $plan->fingerprint())
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();
        } catch (\Throwable $e) {
            Log::warning('QueryPlanRefiner: plan lookup failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($row === null) {
            return null;
        }

        $stored = json_decode((string) $row->plan, true);

        if (! is_array($stored)) {
            return null;
        }

        /*
         | العدّاد يكشف الاستعلامات التي تستحقّ النقل إلى المعجم
         | المحلّي، فيُستغنى عن النموذج فيها نهائياً.
         */
        DB::table('search_query_plans')
            ->where('id', $row->id)
            ->update([
                'hit_count' => DB::raw('hit_count + 1'),
                'last_used_at' => now(),
            ]);

        return $this->rebuild($plan, $stored);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function rebuild(QueryPlan $original, array $stored): ?QueryPlan
    {
        $terms = $this->tokenizeAll($stored['terms'] ?? []);

        if ($terms === []) {
            return null;
        }

        return new QueryPlan(
            original: $original->original,
            folded: $original->folded,
            terms: $terms,
            phrases: count($terms) >= 2 ? [implode(' ', $terms)] : [],
            mustNot: array_values(array_unique([
                ...$original->mustNot,
                ...$this->tokenizeAll($stored['must_not'] ?? []),
            ])),
            expansions: [],
            filters: $original->filters,
            scripts: $original->scripts,
            intent: $original->intent,
            needsNgram: $original->needsNgram,
            isNaturalLanguage: $original->isNaturalLanguage,
            dataTypeSlug: $original->dataTypeSlug,
            source: 'ai-cached',
        );
    }

    /**
     * @param  array<string, mixed>  $interpretation
     */
    private function remember(
        QueryPlan $original,
        int $projectId,
        string $language,
        QueryPlan $refined,
        array $interpretation
    ): void {
        try {
            DB::table('search_query_plans')->updateOrInsert(
                [
                    'project_id' => $projectId,
                    'language' => $language,
                    'query_hash' => $original->fingerprint(),
                ],
                [
                    'original_query' => mb_substr($original->original, 0, 255, 'UTF-8'),
                    'plan' => json_encode([
                        'terms' => $refined->terms,
                        'must_not' => $refined->mustNot,
                    ], JSON_UNESCAPED_UNICODE),
                    'provider' => mb_substr((string) ($interpretation['source'] ?? 'unknown'), 0, 32, 'UTF-8'),
                    'confidence' => min(1.0, max(0.0, (float) ($interpretation['confidence'] ?? 0.0))),
                    'hit_count' => 0,
                    'last_used_at' => now(),
                    'expires_at' => now()->addDays((int) config('search.ai.plan_cache_days', 30)),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            // الفشل في الحفظ لا يبطل خطةً صالحة بين يدينا.
            Log::warning('QueryPlanRefiner: failed to persist plan', ['error' => $e->getMessage()]);
        }
    }

    // ─── قاطع الدارة ─────────────────────────────────────────────────

    private function circuitIsOpen(): bool
    {
        $threshold = (int) config('search.ai.circuit_breaker.failure_threshold', 3);

        return (int) Cache::get(self::CIRCUIT_KEY, 0) >= $threshold;
    }

    private function recordFailure(): void
    {
        $cooldown = (int) config('search.ai.circuit_breaker.cooldown_seconds', 300);
        $failures = (int) Cache::get(self::CIRCUIT_KEY, 0) + 1;

        Cache::put(self::CIRCUIT_KEY, $failures, $cooldown);
    }

    private function recordSuccess(): void
    {
        Cache::forget(self::CIRCUIT_KEY);
    }
}
