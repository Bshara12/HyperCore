<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query;

/**
 * QueryPlan — التمثيل المُنفَّذ الوحيد لاستعلام المستخدم.
 *
 * هذه الخطة هي العقد الفاصل بين الفهم والتنفيذ:
 *
 *   فوقها  — كل ما يفهم النصّ (المحلّل المحلّي، الاحتياطي الذكي).
 *   تحتها  — كل ما ينفّذ (المستودع، المُرتِّب).
 *
 * لماذا يهمّ هذا؟
 *   في النسخة السابقة كان مخرَج الذكاء الاصطناعي سلسلة نصية تُلصَق
 *   مباشرة في استعلام BOOLEAN MODE. أي محرف يطلقه النموذج كان يغيّر
 *   دلالة الاستعلام — "+" و"-" و"*" و"(" لها معانٍ نحوية هناك — بلا
 *   أي طبقة تتحقق. هنا لا يستطيع أي مصدر، مهما كان، إلا أن يملأ حقولاً
 *   مكتوبة الأنواع: مصطلحات، عبارات، استثناءات، شروط. لا نصّ خام يمرّ.
 *
 * وبما أن الخطة نفسها بغضّ النظر عن مصدرها، فمسار الاستعلام واحد سواء
 * جاء الفهم من المعجم المحلّي أو من نموذج لغوي — وهو ما يجعل الاحتياطي
 * الذكي قابلاً للاختبار بلا شبكة.
 */
final readonly class QueryPlan
{
    /**
     * @param  string  $original  نصّ المستخدم كما ورد، للعرض والتسجيل
     * @param  string  $folded  النصّ بعد التطبيع
     * @param  string[]  $terms  مصطلحات مطبَّعة تُحسب عليها الصلة
     * @param  string[]  $phrases  عبارات متعدّدة الكلمات تُطابَق متجاورة
     * @param  string[]  $mustNot  مصطلحات تُقصي المستند إذا وردت
     * @param  string[]  $expansions  مرادفات ومقابلات صوتية، بوزن أخفّ
     * @param  AttributeFilter[]  $filters  شروط بنيوية
     * @param  string[]  $scripts  أنظمة الكتابة الحاضرة في الاستعلام
     * @param  array{intent:string, confidence:float}  $intent
     */
    public function __construct(
        public string $original,
        public string $folded,
        public array $terms = [],
        public array $phrases = [],
        public array $mustNot = [],
        public array $expansions = [],
        public array $filters = [],
        public array $scripts = [],
        public array $intent = ['intent' => 'general', 'confidence' => 0.0],
        public bool $needsNgram = false,
        public bool $isNaturalLanguage = false,
        public ?string $dataTypeSlug = null,
        public string $source = 'local',
    ) {}

    public static function empty(string $original = ''): self
    {
        return new self(original: $original, folded: '');
    }

    /**
     * هل توجد أي إشارة استرجاع؟
     *
     * خطة بلا مصطلحات ولا عبارات ولا شروط لا يمكن تنفيذها: البحث
     * بالاستثناء وحده ("بدون كفر") يعني عرض كل شيء عدا شيء، وهو
     * تصفّح لا بحث — يعالجه المستودع بمسار منفصل.
     */
    public function isExecutable(): bool
    {
        return $this->terms !== [] || $this->phrases !== [] || $this->hardFilters() !== [];
    }

    public function isExclusionOnly(): bool
    {
        return $this->terms === [] && $this->phrases === [] && $this->mustNot !== [];
    }

    /**
     * الشروط التي بلغت ثقتها حدّ الإقصاء.
     *
     * @return AttributeFilter[]
     */
    public function hardFilters(): array
    {
        return array_values(array_filter(
            $this->filters,
            static fn (AttributeFilter $f): bool => $f->isHard()
        ));
    }

    /**
     * الشروط التي تُرجّح ولا تُقصي.
     *
     * @return AttributeFilter[]
     */
    public function softFilters(): array
    {
        return array_values(array_filter(
            $this->filters,
            static fn (AttributeFilter $f): bool => ! $f->isHard()
        ));
    }

    /**
     * كل ما يُطابَق نصّياً: المصطلحات ثم التوسعات.
     *
     * الترتيب مقصود — المستهلكون يعطون وزناً متناقصاً حسب الموضع، فيجب
     * أن يسبق ما كتبه المستخدم فعلاً ما استنتجناه نحن.
     *
     * @return string[]
     */
    public function allTerms(): array
    {
        return array_values(array_unique([...$this->terms, ...$this->expansions]));
    }

    /**
     * بصمة الاستعلام: مفتاح الكاش وسجلّ الخطط.
     *
     * تُبنى من النصّ المطبَّع لا الخام، فتتوحّد "IPHONE" و"iphone"
     * و"ＩＰＨＯＮＥ" على مدخل كاش واحد.
     */
    public function fingerprint(): string
    {
        return hash('xxh128', $this->folded);
    }

    public function withFilters(array $filters): self
    {
        return $this->copyWith(['filters' => array_values($filters)]);
    }

    /**
     * نسخة بمصطلحات مختلفة، مع إعادة اشتقاق العبارة منها.
     *
     * إعادة اشتقاق العبارة ليست تفصيلاً: العبارة مبنية من المصطلحات،
     * فإبقاؤها على صورتها القديمة بعد تصحيح إملائي يعني البحث عن تجاور
     * كلماتٍ لم تعد في الخطة — مكافأةُ تجاورٍ لا يمكن أن تتحقّق أبداً.
     *
     * @param  string[]  $terms
     */
    public function withTerms(array $terms): self
    {
        $terms = array_values(array_unique($terms));

        return $this->copyWith([
            'terms' => $terms,
            'phrases' => count($terms) >= 2 ? [implode(' ', $terms)] : [],
        ]);
    }

    public function withIntent(array $intent): self
    {
        return $this->copyWith(['intent' => $intent]);
    }

    public function withSource(string $source): self
    {
        return $this->copyWith(['source' => $source]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original' => $this->original,
            'folded' => $this->folded,
            'terms' => $this->terms,
            'phrases' => $this->phrases,
            'must_not' => $this->mustNot,
            'expansions' => $this->expansions,
            'filters' => array_map(
                static fn (AttributeFilter $f): array => $f->toArray(),
                $this->filters
            ),
            'scripts' => $this->scripts,
            'intent' => $this->intent,
            'needs_ngram' => $this->needsNgram,
            'is_natural_language' => $this->isNaturalLanguage,
            'data_type_slug' => $this->dataTypeSlug,
            'source' => $this->source,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function copyWith(array $changes): self
    {
        return new self(
            original: $changes['original'] ?? $this->original,
            folded: $changes['folded'] ?? $this->folded,
            terms: $changes['terms'] ?? $this->terms,
            phrases: $changes['phrases'] ?? $this->phrases,
            mustNot: $changes['mustNot'] ?? $this->mustNot,
            expansions: $changes['expansions'] ?? $this->expansions,
            filters: $changes['filters'] ?? $this->filters,
            scripts: $changes['scripts'] ?? $this->scripts,
            intent: $changes['intent'] ?? $this->intent,
            needsNgram: $changes['needsNgram'] ?? $this->needsNgram,
            isNaturalLanguage: $changes['isNaturalLanguage'] ?? $this->isNaturalLanguage,
            dataTypeSlug: $changes['dataTypeSlug'] ?? $this->dataTypeSlug,
            source: $changes['source'] ?? $this->source,
        );
    }
}
