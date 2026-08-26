<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query;

use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Lexicon\ProjectSynonyms;
use App\Domains\Search\Support\Query\Extractors\LexicalAttributeExtractor;
use App\Domains\Search\Support\Query\Extractors\NegationExtractor;
use App\Domains\Search\Support\Query\Extractors\NumericExtractor;
use App\Domains\Search\Support\Query\Extractors\TemporalExtractor;
use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use App\Domains\Search\Support\Text\UnicodeScript;

/**
 * QueryAnalyzer — تحويل نصّ المستخدم إلى QueryPlan قابلة للتنفيذ.
 *
 * هذا هو المسار المحلّي: حتمي، بلا شبكة، وبزمن ثابت من رتبة الميلي ثانية.
 * يعمل على كل استعلام دون استثناء. الاحتياطي الذكي لا يُستدعى إلا بعد
 * أن يفشل هذا المسار في إيجاد أي نتيجة — لا قبله ولا بالتوازي معه.
 *
 * ─── لماذا محلّي أولاً ───────────────────────────────────────────────
 *
 *   النسخة السابقة كانت ترسل كل استعلام عربي تقريباً إلى نموذج لغوي،
 *   لأن ArabicQueryNormalizer كان يضع isNaturalLanguage=true لمجرّد
 *   وجود حرف عربي. النتيجة: كل بحث بالعربية يدفع كلفة استدعاء شبكي،
 *   وزمن استجابته غير قابل للتنبؤ، ونتائجه غير قابلة لإعادة الإنتاج
 *   في الاختبار. والأسوأ أن عطل المزوّد كان يعطّل البحث العربي كله.
 *
 *   الفهم البنيوي — النفي والزمن والأرقام والسمات والنية — لا يحتاج
 *   نموذجاً لغوياً أصلاً. يحتاج معجماً ونحواً بسيطاً. وهو ما هنا.
 *
 * ─── ترتيب المراحل، ولماذا هو كذلك ──────────────────────────────────
 *
 *   1. التطبيع       — كل ما بعده يفترض صورة موحّدة
 *   2. الـ scripts    — تحدّد أي موارد معجمية تُحمَّل
 *   3. التقسيم       — واعٍ بالـ script (مسافات أم n-grams)
 *   4. النفي         — يعيد بناء تيار الوحدات، فيسبق كل تحليل عليه
 *   5. النية         — يحتاجها المستخرِج العددي لترجيح "أقل من 500"
 *   6. الاستخراج     — زمن، أرقام، سمات
 *   7. التنقية       — حذف المستهلَك وكلمات الوقف والحشو
 *   8. التوسعة       — ترجمة صوتية ومرادفات، بوزن أخفّ
 */
final class QueryAnalyzer
{
    private const INTENT_CONFIDENCE_THRESHOLD = 0.30;

    private const NATURAL_LANGUAGE_MIN_WORDS = 5;

    public function __construct(
        private readonly Lexicon $lexicon,
        private readonly ProjectSynonyms $synonyms,
        private readonly NegationExtractor $negation,
        private readonly TemporalExtractor $temporal,
        private readonly NumericExtractor $numeric,
        private readonly LexicalAttributeExtractor $attributes,
    ) {}

    /**
     * @param  int|null  $projectId  حين يُمرَّر تُضاف مرادفات المشروع المتعلَّمة
     */
    public function analyze(
        string $rawQuery,
        ?string $dataTypeSlug = null,
        ?int $projectId = null,
        ?string $language = null
    ): QueryPlan {
        $folded = TextFolder::fold($rawQuery);

        if ($folded === '') {
            return QueryPlan::empty($rawQuery);
        }

        $scripts = $this->resolveScripts($folded);
        $tokens = Segmenter::tokenize($folded);

        if ($tokens === []) {
            return QueryPlan::empty($rawQuery);
        }

        // ─── النفي: يعيد بناء تيار الوحدات ────────────────────────────
        $split = $this->negation->extract($tokens, $scripts);
        $included = $split['include'];

        // ─── النية: يحتاجها المستخرِج العددي ──────────────────────────
        $intent = $this->detectIntent($included, $scripts);

        /*
         | ─── الاستخراج البنيوي ───────────────────────────────────────
         |
         | يتلقّى المستخرِجون النصّ المطبَّع كاملاً لا الوحدات مُعاد
         | ضمّها. الفرق حاسم: التقسيم يُسقط الترقيم، بينما الترقيم نفسه
         | هو حامل المعنى في أنماط كاملة —
         |
         |     "2018-2020"  الواصل هو ما يجعلها نطاقاً لا سنتين
         |     "$500"       رمز العملة هو ما يجعلها سعراً لا رقماً
         |     "6.7 inch"   النقطة العشرية جزء من القيمة
         |
         | أمّا الوحدات فتُمرَّر إلى جانبه لتحديد ما يُستهلَك منها، وهي
         | الوحدات بعد النفي كي لا يتحوّل المستثنى إلى شرط مفروض.
         */
        $extraction = $this->temporal->extract($folded, $included, $scripts)
            ->merge($this->numeric->extract($folded, $included, $scripts, $intent))
            ->merge($this->attributes->extract($included, $scripts));

        // ─── التنقية ──────────────────────────────────────────────────
        $terms = $this->buildTerms($included, $extraction->consumed, $scripts);

        return new QueryPlan(
            original: $rawQuery,
            folded: $folded,
            terms: $terms,
            phrases: $this->buildPhrases($terms),
            mustNot: $this->cleanExclusions($split['exclude'], $scripts, $terms),
            expansions: $this->buildExpansions($terms, $scripts, $projectId, $language),
            filters: $this->limitFilters($extraction->filters),
            scripts: $scripts,
            intent: $intent,
            needsNgram: UnicodeScript::needsNgram($folded),
            isNaturalLanguage: $this->looksNatural($included, $split['hadNegation'], $scripts),
            dataTypeSlug: $dataTypeSlug,
            source: 'local',
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * أنظمة الكتابة التي تُحمَّل مواردها.
     *
     * تُستعمل عتبة منخفضة (0.10) لا العتبة الافتراضية: كلمة عربية واحدة
     * في استعلام إنجليزي طويل تظلّ تحتاج معجمها العربي لتُفهَم، حتى لو
     * لم تبلغ نسبتها ربع النص.
     *
     * @return string[]
     */
    private function resolveScripts(string $folded): array
    {
        $present = UnicodeScript::present($folded, 0.10);

        /*
         | استعلام أرقام بحت ("2020") لا script فيه أصلاً. نُسند اللاتيني
         | كي تتوفّر دوالّ النطاق والوحدات، وكلها بصور ASCII.
         */
        return $present === [] ? [UnicodeScript::LATIN] : $present;
    }

    /**
     * @param  string[]  $tokens
     * @param  string[]  $scripts
     * @return array{intent:string, confidence:float, scores:array<string,float>}
     */
    private function detectIntent(array $tokens, array $scripts): array
    {
        $signals = $this->lexicon->intentSignals($scripts);
        $scores = ['buy' => 0.0, 'repair' => 0.0, 'compare' => 0.0, 'learn' => 0.0];

        foreach ($tokens as $token) {
            $signal = $signals[$token] ?? null;

            if ($signal === null) {
                continue;
            }

            [$intent, $weight] = $signal;

            if (isset($scores[$intent])) {
                $scores[$intent] += (float) $weight;
            }
        }

        $total = array_sum($scores);

        if ($total <= 0.0) {
            return ['intent' => 'general', 'confidence' => 0.0, 'scores' => $scores];
        }

        $normalized = array_map(
            static fn (float $score): float => round($score / $total, 4),
            $scores
        );

        arsort($normalized);
        $winner = (string) array_key_first($normalized);
        $confidence = (float) $normalized[$winner];

        return $confidence < self::INTENT_CONFIDENCE_THRESHOLD
            ? ['intent' => 'general', 'confidence' => $confidence, 'scores' => $normalized]
            : ['intent' => $winner, 'confidence' => $confidence, 'scores' => $normalized];
    }

    /**
     * المصطلحات النهائية: ما بقي بعد حذف المستهلَك وكلمات الوقف والحشو.
     *
     * الشبكة الاحتياطية في آخر الدالة ليست تفصيلاً: استعلام يتكوّن كلّه
     * من كلمات وقف ("how to") أو من حشو ("بدي") سيصير فارغاً، فيرجع صفر
     * نتائج على استعلام له معنى. حين لا يبقى شيء نُرجع الوحدات كما هي
     * ونترك BM25 يقرّر — نتيجة ضعيفة أفضل من لا نتيجة.
     *
     * @param  string[]  $tokens
     * @param  int[]  $consumed
     * @param  string[]  $scripts
     * @return string[]
     */
    private function buildTerms(array $tokens, array $consumed, array $scripts): array
    {
        $consumedSet = array_flip($consumed);
        $stopwords = $this->lexicon->stopwords($scripts);
        $fillers = $this->lexicon->fillers($scripts);

        $terms = [];

        foreach ($tokens as $index => $token) {
            if (isset($consumedSet[$index])) {
                continue;
            }

            if (isset($stopwords[$token]) || isset($fillers[$token])) {
                continue;
            }

            $terms[] = $token;
        }

        $terms = array_values(array_unique($terms));

        if ($terms === []) {
            $terms = array_values(array_unique(array_filter(
                $tokens,
                static fn (string $t): bool => ! isset($fillers[$t])
            )));
        }

        if ($terms === []) {
            $terms = array_values(array_unique($tokens));
        }

        return array_slice($terms, 0, (int) config('search.understanding.max_terms', 12));
    }

    /**
     * العبارة الكاملة كوحدة مطابقة.
     *
     * مستند يحتوي "iphone 15 pro" متجاورةً أوثق صلةً بلا مقارنة من
     * مستند يذكر الكلمات الثلاث متفرّقة في فقرات مختلفة. BM25 وحده لا
     * يرى التجاور — يعدّ التكرارات فقط. هذه العبارة هي ما يعوّض ذلك.
     *
     * @param  string[]  $terms
     * @return string[]
     */
    private function buildPhrases(array $terms): array
    {
        return count($terms) < 2 ? [] : [implode(' ', $terms)];
    }

    /**
     * تنقية الاستثناءات من كلمات الوقف ومن المصطلحات المطلوبة.
     *
     * ─── لماذا يُطرح المطلوب من المستثنى ────────────────────────────
     *
     * "بدي ايفون بس مو ايفون 14" تضع "ايفون" في الطرفين: مطلوبةً في
     * الشطر الأول ومستثناةً في الثاني. ولا يمكن لمستند أن يحتوي كلمة
     * ولا يحتويها، فالشرطان معاً يُنتجان مجموعة فارغة بالضرورة.
     *
     * وقد كان الأمر ينجح عرَضاً حين يكون المحتوى بلغة أخرى — فـ"ايفون"
     * المستثناة لا ترد في عنوان إنجليزي مكتوب "iPhone" — ويفشل فشلاً
     * تامّاً على محتوى عربي. أي خطأ يعتمد نجاحُه على لغة المحتوى.
     *
     * المطلوب يغلب لأن المستخدم ذكره إيجاباً أولاً، ويبقى في الاستثناء
     * ما يميّز فعلاً: "14" — وهي بالضبط الكلمة التي قصد نفيها.
     *
     * "بدون الكفر" — استثناء "ال" يعني إقصاء كل مستند تقريباً.
     *
     * @param  string[]  $exclusions
     * @param  string[]  $scripts
     * @param  string[]  $terms
     * @return string[]
     */
    private function cleanExclusions(array $exclusions, array $scripts, array $terms): array
    {
        $stopwords = $this->lexicon->stopwords($scripts);
        $fillers = $this->lexicon->fillers($scripts);
        $wanted = array_flip($terms);

        return array_values(array_filter(
            $exclusions,
            static fn (string $t): bool => ! isset($stopwords[$t])
                && ! isset($fillers[$t])
                && ! isset($wanted[$t])
                && mb_strlen($t, 'UTF-8') >= 2
        ));
    }

    /**
     * التوسعات: المقابل الصوتي اللاتيني للمصطلحات غير اللاتينية.
     *
     * تُفصَل عن المصطلحات لا تُدمَج فيها، لأن المُرتِّب يعطيها وزناً أخفّ:
     * من كتب "ايفون" يريد الآيفون، لكن مطابقة "iphone" استنتاجٌ منّا
     * لا نصٌّ منه، فلا يصحّ أن تُساوي مطابقة ما كتبه فعلاً.
     *
     * @param  string[]  $terms
     * @param  string[]  $scripts
     * @return string[]
     */
    private function buildExpansions(
        array $terms,
        array $scripts,
        ?int $projectId,
        ?string $language
    ): array {
        $expansions = [];

        /*
         | تجريد السوابق أولاً، لأن التوسعات التالية تُبنى على الناتج.
         |
         | "الايفون" لا مقابل لها في خريطة الترجمة الصوتية، فبلا تجريدها
         | لا يُولَّد "iphone" ولا تطابق محتوى عنوانه "iPhone 14" —
         | بينما "ايفون" تطابقه. أي أن أداة تعريف واحدة كانت تُسقط
         | الاستعلام كلياً.
         */
        $stripped = $this->stripPrefixes($terms, $scripts);
        $searchable = [...$terms, ...$stripped];

        foreach ($this->lexicon->transliterations($scripts) as $source => $latin) {
            if (in_array((string) $source, $searchable, true) && (string) $latin !== (string) $source) {
                $expansions[] = (string) $latin;
            }
        }

        foreach ($stripped as $bare) {
            $expansions[] = $bare;
        }

        /*
         | مرادفات المشروع المتعلَّمة من سلوك مستخدميه.
         |
         | المعجم الثابت يعرف أن "phone" و"mobile" مترادفتان في كل
         | مكان، أمّا أن "قماش" و"خام" مترادفتان في متجر أقمشة بعينه
         | فلا يعرفه إلا سلوك مستخدمي ذلك المتجر.
         */
        if ($projectId !== null && $language !== null) {
            foreach ($this->synonyms->expand($terms, $projectId, $language) as $synonym) {
                $expansions[] = $synonym;
            }
        }

        return array_values(array_unique(array_diff($expansions, $terms)));
    }

    /**
     * الصور المجرَّدة من السوابق.
     *
     * الحدّ الأدنى للطول بعد التجريد يمنع تحويل كلمات قصيرة إلى بقايا
     * بلا معنى: "الام" لو جُرِّدت لصارت "ام" — كلمة مختلفة لا صورة أخرى
     * من الأولى.
     *
     * @param  string[]  $terms
     * @param  string[]  $scripts
     * @return string[]
     */
    private function stripPrefixes(array $terms, array $scripts): array
    {
        $prefixes = $this->lexicon->strippablePrefixes($scripts);

        if ($prefixes === []) {
            return [];
        }

        $stripped = [];

        foreach ($terms as $term) {
            foreach ($prefixes as $prefix) {
                if (! str_starts_with($term, $prefix)) {
                    continue;
                }

                $bare = mb_substr($term, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8');

                if (mb_strlen($bare, 'UTF-8') >= 3 && ! in_array($bare, $terms, true)) {
                    $stripped[] = $bare;
                }

                break;
            }
        }

        return array_values(array_unique($stripped));
    }

    /**
     * هل الاستعلام صياغة طبيعية لا كلمات مفتاحية؟
     *
     * تُستعمل هذه الإشارة في قرار استدعاء الاحتياطي الذكي: استعلام
     * بكلمتين مفتاحيتين لم يجد نتائج غالباً لا وجود له في المحتوى،
     * بينما جملة كاملة لم تجد نتائج قد تكون سوء فهم يستحقّ محاولة ثانية.
     *
     * @param  string[]  $tokens
     * @param  string[]  $scripts
     */
    private function looksNatural(array $tokens, bool $hadNegation, array $scripts): bool
    {
        if ($hadNegation) {
            return true;
        }

        if (count($tokens) >= self::NATURAL_LANGUAGE_MIN_WORDS) {
            return true;
        }

        $fillers = $this->lexicon->fillers($scripts);

        foreach ($tokens as $token) {
            if (isset($fillers[$token])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  AttributeFilter[]  $filters
     * @return AttributeFilter[]
     */
    private function limitFilters(array $filters): array
    {
        $unique = [];

        foreach ($filters as $filter) {
            $key = $filter->fingerprint();

            if (! isset($unique[$key]) || $filter->confidence > $unique[$key]->confidence) {
                $unique[$key] = $filter;
            }
        }

        return array_slice(
            array_values($unique),
            0,
            (int) config('search.understanding.max_filters', 6)
        );
    }
}
