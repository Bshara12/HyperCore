<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Indexing;

use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Text\TextFolder;

/**
 * AttributeNormalizer — الجسر بين حقول المحتوى وشروط الاستعلام.
 *
 * ─── المشكلة ────────────────────────────────────────────────────────
 *
 * طرفا المعادلة يُكتبان على حدة:
 *
 *   جانب المحتوى   — صاحب الموقع ينشئ حقلاً ويسمّيه "Release Year"
 *                     أو "release_year" أو "سنة الإصدار".
 *
 *   جانب الاستعلام — المحلّل يرى "نزل بال 2020" فينتج مفتاح "year".
 *
 * بلا توحيد يبحث الطرفان في مفتاحين مختلفين فلا يلتقيان، مهما بلغت
 * جودة الاستخراج على كلا الجانبين.
 *
 * ─── القاعدة ────────────────────────────────────────────────────────
 *
 *   1. طبّع اسم الحقل (حالة الأحرف، الفواصل، العلامات).
 *   2. حوّله إلى المفتاح القانوني إن وُجد له مرادف.
 *   3. وإلا أبقه باسمه المطبَّع — فلا يضيع حقل، لكن لن تصله أسئلة
 *      اللغة الطبيعية إلا إذا أُضيف مرادفه إلى المعجم.
 */
final class AttributeNormalizer
{
    private const MAX_KEY_LENGTH = 64;

    private const MAX_TEXT_LENGTH = 191;

    /** الحدّ الأدنى والأقصى لما يُقبل سنةً عند استنتاج النوع من تاريخ. */
    private const MIN_YEAR = 1900;

    private const MAX_YEAR = 2100;

    /** @var array<string, string>|null */
    private ?array $aliases = null;

    public function __construct(
        private readonly Lexicon $lexicon,
    ) {}

    /**
     * تحويل اسم حقل إلى مفتاح سمة قانوني.
     */
    public function key(string $fieldName): string
    {
        $folded = $this->foldKey($fieldName);

        if ($folded === '') {
            return '';
        }

        return mb_substr($this->aliases()[$folded] ?? $folded, 0, self::MAX_KEY_LENGTH, 'UTF-8');
    }

    /**
     * تحويل قيمة حقل خام إلى صفّ سمة.
     *
     * ─── لماذا نحاول العدد أولاً دائماً ─────────────────────────────
     *
     * "128" المخزَّنة نصّاً أصغر من "64" في أي ترتيب معجمي، و"أقل من
     * 500" لا تطابق سعراً مخزَّناً "1200". المقارنة العددية — وهي مقصد
     * أغلب الشروط البنيوية — تحتاج نوعاً عددياً حقيقياً.
     *
     * القيمة تُخزَّن في العمودين معاً حين تكون عدداً: العددي للمقارنة،
     * والنصّي للمطابقة التامّة وللعرض.
     *
     * @return array{key: string, value_text: ?string, value_num: ?float}|null
     */
    public function value(string $fieldName, mixed $rawValue): ?array
    {
        $key = $this->key($fieldName);

        if ($key === '') {
            return null;
        }

        $text = $this->stringify($rawValue);

        if ($text === null) {
            return null;
        }

        return [
            'key' => $key,
            'value_text' => mb_substr($text, 0, self::MAX_TEXT_LENGTH, 'UTF-8'),
            'value_num' => $this->numeric($key, $text),
        ];
    }

    /**
     * استخراج كل السمات من خريطة حقول.
     *
     * @param  array<string, mixed>  $fields
     * @return array<int, array{key: string, value_text: ?string, value_num: ?float}>
     */
    public function fromFields(array $fields): array
    {
        $attributes = [];

        foreach ($fields as $name => $rawValue) {
            $attribute = $this->value((string) $name, $rawValue);

            if ($attribute === null) {
                continue;
            }

            /*
             | مفتاح واحد لكل مستند: القيد الفريد على الجدول يمنع
             | الازدواج، فنحسمه هنا بدل أن ينفجر الإدراج المجمَّع.
             | الأولوية للأولى — أول حقل يحمل الاسم هو الأصل عادةً.
             */
            $attributes[$attribute['key']] ??= $attribute;
        }

        return array_values($attributes);
    }

    // ─────────────────────────────────────────────────────────────────

    private function foldKey(string $name): string
    {
        $folded = TextFolder::fold($name);
        $folded = preg_replace('/[^\p{L}\p{N}]+/u', '_', $folded) ?? $folded;

        return trim($folded, '_');
    }

    /**
     * تحويل قيمة حقل خام إلى نصّ قابل للتخزين.
     *
     * المصفوفات (حقول متعدّدة القيم) تُضمّ بفاصلة؛ القيم المنطقية
     * تُوحَّد إلى yes/no كي تطابق ما يكتبه المستخدمون.
     */
    private function stringify(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_scalar($value)) {
            $text = TextFolder::fold((string) $value);

            return $text === '' ? null : $text;
        }

        if (is_array($value)) {
            $parts = array_filter(array_map(
                fn ($item): ?string => is_scalar($item) ? TextFolder::fold((string) $item) : null,
                $value
            ));

            return $parts === [] ? null : implode(',', $parts);
        }

        return null;
    }

    /**
     * القيمة العددية إن أمكن.
     *
     * السنة تُعالَج على حدة: الحقل قد يحمل تاريخاً كاملاً
     * ("2020-09-15") بينما يسأل المستخدم عن سنة. استخراج السنة من
     * التاريخ هو ما يجعل "نزل بال 2020" يطابق مستنداً تاريخُ إصداره
     * يوم بعينه من تلك السنة.
     */
    private function numeric(string $key, string $text): ?float
    {
        if ($key === 'year') {
            return $this->year($text);
        }

        /*
         | فواصل الآلاف ورموز العملة تُنزع قبل التحويل: "1,299" و"$1299"
         | كلاهما 1299، وتركهما يعني فقدان القيمة العددية لأشيع صيغة
         | تُكتب بها الأسعار.
         */
        $cleaned = preg_replace('/[^\d.\-]/u', '', str_replace(',', '', $text)) ?? '';

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function year(string $text): ?float
    {
        if (preg_match('/\b(1[89]\d{2}|20\d{2}|21\d{2})\b/u', $text, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];

        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR ? (float) $year : null;
    }

    /**
     * @return array<string, string>
     */
    private function aliases(): array
    {
        if ($this->aliases !== null) {
            return $this->aliases;
        }

        /*
         | المرادفات في القسم المشترك، فتُقرأ بأي مجموعة scripts.
         | نمرّر مجموعة فارغة كي لا نحمّل موارد لغة بلا داعٍ.
         */
        $raw = $this->lexicon->for([])['attribute_aliases'] ?? [];
        $normalized = [];

        foreach ($raw as $alias => $canonical) {
            $normalized[$this->foldKey((string) $alias)] = $this->foldKey((string) $canonical);
        }

        return $this->aliases = $normalized;
    }
}
