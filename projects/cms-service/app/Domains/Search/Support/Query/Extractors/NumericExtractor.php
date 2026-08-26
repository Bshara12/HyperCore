<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query\Extractors;

use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Query\AttributeFilter;

/**
 * NumericExtractor — الأرقام ذات المعنى القياسي: سعة، سعر، حجم، سرعة.
 *
 * ─── القاعدة الحاكمة: لا نخترع مفتاحاً ──────────────────────────────
 *
 *   لا يُنتَج شرط عددي إلا حين نستطيع تسمية السمة التي يقيسها:
 *
 *     "128gb"        → وحدة معروفة   ⇒ storage = 128
 *     "$500"         → رمز عملة      ⇒ price   = 500
 *     "under 500"    → دالّ نطاق     ⇒ price   ≤ 500  (نيّة شرائية)
 *     "iphone 15"    → لا وحدة ولا دالّ ⇒ لا شرط، يبقى مصطلحاً
 *
 *   السطر الأخير هو المهم. الرقم المجرّد في هذا المجال اسمُ موديل غالباً
 *   لا مقدار، وتحويله إلى شرط عددي كان سيُقصي "iPhone 15 Pro" من نتائج
 *   البحث عن "iphone 15". حين لا نعرف ما يقيسه الرقم، لا نتصرّف.
 *
 * ─── لماذا تُخزَّن الوحدات كأرقام ────────────────────────────────────
 *
 *   "128gb" مخزَّنةً نصّاً لا تطابق "أكثر من 64 جيجا"، لأن المقارنة
 *   النصّية لا ترتّب. مخزَّنةً كـ storage=128 تطابقها ويصحّ ترتيبها.
 *   ولهذا تُوحَّد الوحدات إلى مقياس واحد: 1tb تُخزَّن 1024 لا 1.
 */
final class NumericExtractor
{
    public const KEY_PRICE = 'price';

    /** رقم متبوعاً بوحدة، بمسافة أو بدونها: "128gb"، "6.7 inch" */
    private const VALUE_UNIT_PATTERN = '/(\d+(?:\.\d+)?)\s*([a-z"]{1,4})\b/u';

    /** رقم مسبوقاً برمز عملة: "$500"، "€1200" */
    private const CURRENCY_PREFIX_PATTERN = '/([$€£¥₹﷼])\s*(\d+(?:\.\d+)?)/u';

    /** رقم متبوعاً برمز عملة أو رمزها الحرفي: "500$"، "500 usd" */
    private const CURRENCY_SUFFIX_PATTERN = '/(\d+(?:\.\d+)?)\s*([$€£¥₹﷼]|usd|eur|gbp|sar|aed|egp)\b/u';

    public function __construct(
        private readonly Lexicon $lexicon,
    ) {}

    /**
     * @param  string[]  $tokens
     * @param  string[]  $scripts
     * @param  array{intent:string, confidence:float}  $intent
     */
    public function extract(
        string $foldedQuery,
        array $tokens,
        array $scripts,
        array $intent = ['intent' => 'general', 'confidence' => 0.0]
    ): ExtractionResult {
        $result = $this->extractUnits($foldedQuery, $tokens, $scripts);
        $result = $result->merge($this->extractCurrency($foldedQuery, $tokens));
        $result = $result->merge($this->extractRanges($foldedQuery, $tokens, $scripts, $intent));

        return $this->deduplicate($result);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  string[]  $tokens
     * @param  string[]  $scripts
     */
    private function extractUnits(string $foldedQuery, array $tokens, array $scripts): ExtractionResult
    {
        $units = $this->lexicon->units($scripts);

        if ($units === [] || preg_match_all(self::VALUE_UNIT_PATTERN, $foldedQuery, $matches, PREG_SET_ORDER) === 0) {
            return ExtractionResult::empty();
        }

        $filters = [];
        $consumed = [];

        foreach ($matches as $match) {
            $unit = $units[$match[2]] ?? null;

            if ($unit === null) {
                continue;
            }

            $value = (float) $match[1] * $unit['factor'];

            $filters[] = AttributeFilter::numericEquals((string) $unit['key'], $value, 0.85);
            $consumed = [...$consumed, ...$this->indexesOf($tokens, $match[0], $match[1], $match[2])];
        }

        return new ExtractionResult($filters, $consumed);
    }

    /**
     * @param  string[]  $tokens
     */
    private function extractCurrency(string $foldedQuery, array $tokens): ExtractionResult
    {
        $filters = [];
        $consumed = [];

        foreach ([[self::CURRENCY_PREFIX_PATTERN, 2, 1], [self::CURRENCY_SUFFIX_PATTERN, 1, 2]] as [$pattern, $numberGroup, $symbolGroup]) {
            if (preg_match_all($pattern, $foldedQuery, $matches, PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                $filters[] = AttributeFilter::numericEquals(
                    self::KEY_PRICE,
                    (float) $match[$numberGroup],
                    0.85
                );

                $consumed = [
                    ...$consumed,
                    ...$this->indexesOf($tokens, $match[0], $match[$numberGroup], $match[$symbolGroup]),
                ];
            }
        }

        return new ExtractionResult($filters, $consumed);
    }

    /**
     * نطاقات معبَّر عنها بدوالّ لغوية: "أقل من 500"، "between 200 and 500".
     *
     * الثقة هنا مشروطة بالسياق. "under 500" بلا رمز عملة قد تعني سعراً
     * وقد تعني أي شيء آخر، فتبقى ترجيحاً — إلا إذا دلّت نيّة الاستعلام
     * على الشراء، فيصير السعر التفسير الوحيد المعقول.
     *
     * @param  string[]  $tokens
     * @param  string[]  $scripts
     * @param  array{intent:string, confidence:float}  $intent
     */
    private function extractRanges(
        string $foldedQuery,
        array $tokens,
        array $scripts,
        array $intent
    ): ExtractionResult {
        $cues = $this->lexicon->rangeCues($scripts);

        if ($cues === []) {
            return ExtractionResult::empty();
        }

        $hasCurrency = $this->mentionsCurrency($foldedQuery, $scripts);
        $isBuying = $intent['intent'] === 'buy' && $intent['confidence'] >= 0.3;

        $confidence = match (true) {
            $hasCurrency => 0.90,
            $isBuying => 0.85,
            default => 0.50,
        };

        $filters = [];
        $consumed = [];

        foreach ($cues as $cue => $operator) {
            if ($cue === '' || ! str_contains($foldedQuery, $cue)) {
                continue;
            }

            $bounds = $this->numbersAfter($foldedQuery, $cue, $operator === 'range' ? 2 : 1);

            if ($bounds === []) {
                continue;
            }

            $filter = match ($operator) {
                'lte' => AttributeFilter::numericRange(self::KEY_PRICE, null, $bounds[0], $confidence),
                'gte' => AttributeFilter::numericRange(self::KEY_PRICE, $bounds[0], null, $confidence),
                'range' => count($bounds) >= 2
                    ? AttributeFilter::numericRange(self::KEY_PRICE, min($bounds), max($bounds), $confidence)
                    : null,
                default => null,
            };

            if ($filter === null) {
                continue;
            }

            $filters[] = $filter;

            if ($filter->isHard()) {
                $consumed = [...$consumed, ...$this->indexesOfNumbers($tokens, $bounds)];
            }
        }

        return new ExtractionResult($filters, $consumed);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  string[]  $scripts
     */
    private function mentionsCurrency(string $foldedQuery, array $scripts): bool
    {
        foreach ($this->lexicon->currencySymbols($scripts) as $symbol) {
            if ($symbol !== '' && str_contains($foldedQuery, $symbol)) {
                return true;
            }
        }

        return false;
    }

    /**
     * الأرقام التي تلي دالّاً مباشرةً.
     *
     * @return float[]
     */
    private function numbersAfter(string $foldedQuery, string $cue, int $count): array
    {
        $position = mb_strpos($foldedQuery, $cue, 0, 'UTF-8');

        if ($position === false) {
            return [];
        }

        $tail = mb_substr($foldedQuery, $position + mb_strlen($cue, 'UTF-8'), null, 'UTF-8');

        if (preg_match_all('/\d+(?:\.\d+)?/u', $tail, $matches) === 0) {
            return [];
        }

        return array_map('floatval', array_slice($matches[0], 0, $count));
    }

    /**
     * فهارس الوحدات التي يغطّيها تعبير مطابَق.
     *
     * @param  string[]  $tokens
     * @return int[]
     */
    private function indexesOf(array $tokens, string ...$fragments): array
    {
        $wanted = [];

        foreach ($fragments as $fragment) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', $fragment, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
                $wanted[$piece] = true;
            }
        }

        $indexes = [];

        foreach ($tokens as $index => $token) {
            if (isset($wanted[$token])) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @param  string[]  $tokens
     * @param  float[]  $numbers
     * @return int[]
     */
    private function indexesOfNumbers(array $tokens, array $numbers): array
    {
        $wanted = [];

        foreach ($numbers as $number) {
            $wanted[(string) $number] = true;
            $wanted[(string) (int) $number] = true;
        }

        $indexes = [];

        foreach ($tokens as $index => $token) {
            if (isset($wanted[$token])) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * إزالة الشروط المكرّرة، بالإبقاء على الأعلى ثقة.
     *
     * التكرار وارد بنيوياً: "$500" يطابق نمط العملة، وقد تلتقطه دالّة
     * نطاق في الجملة نفسها. المكرّر يضاعف أثر الشرط في الترتيب بلا وجه.
     */
    private function deduplicate(ExtractionResult $result): ExtractionResult
    {
        $best = [];

        foreach ($result->filters as $filter) {
            $key = $filter->fingerprint();

            if (! isset($best[$key]) || $filter->confidence > $best[$key]->confidence) {
                $best[$key] = $filter;
            }
        }

        $max = (int) config('search.understanding.max_filters', 6);

        return new ExtractionResult(
            filters: array_slice(array_values($best), 0, $max),
            consumed: $result->consumed,
        );
    }
}
