<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query;

/**
 * AttributeFilter — شرط بنيوي مستخرج من الاستعلام.
 *
 * الفكرة المركزية:
 *   "الايفون يلي نزل بال 2020" ليست مسألة تشابه دلالي — هي شرط.
 *   المستند إما صدر في 2020 أو لم يصدر. لا يوجد "قريب من 2020".
 *
 *   لهذا لا تُحلّ بالـ embeddings: التضمينات تضع 2020 و2021 في جوارٍ
 *   متقارب لأنهما متشابهتان لغوياً، فتُرجع آيفون 2021 ضمن النتائج
 *   الأولى. الحل الصحيح هو استخراج الشرط وتنفيذه على سمة مفهرسة.
 *
 * ─── الثقة، ولماذا هي جوهرية ────────────────────────────────────────
 *
 *   "ايفون 2020"         → الرقم قد يكون سنة وقد يكون اسم موديل.
 *                          ثقة منخفضة ⇒ ترجيح: يرفع المطابق ولا يُقصي.
 *
 *   "ايفون نزل بال 2020" → "نزل" لا تحتمل غير الزمن.
 *                          ثقة عالية ⇒ فلتر: يُقصي فعلاً.
 *
 *   بلا هذا التمييز يكون أمامنا خياران سيّئان: إمّا إقصاء نتائج صحيحة
 *   عند كل رقم، أو تجاهل الأرقام فلا يُفهم الاستعلام أصلاً.
 */
final readonly class AttributeFilter
{
    public const OP_EQUALS = 'eq';

    public const OP_GTE = 'gte';

    public const OP_LTE = 'lte';

    public const OP_RANGE = 'range';

    public const TYPE_NUMERIC = 'num';

    public const TYPE_TEXT = 'text';

    public function __construct(
        public string $key,
        public string $operator,
        public string $type,
        public float|string|null $value,
        public float|string|null $valueTo = null,
        public float $confidence = 1.0,
        public string $source = 'local',
    ) {}

    public static function numericEquals(
        string $key,
        float $value,
        float $confidence = 1.0,
        string $source = 'local'
    ): self {
        return new self($key, self::OP_EQUALS, self::TYPE_NUMERIC, $value, null, $confidence, $source);
    }

    public static function numericRange(
        string $key,
        ?float $from,
        ?float $to,
        float $confidence = 1.0,
        string $source = 'local'
    ): self {
        if ($from !== null && $to !== null) {
            return new self($key, self::OP_RANGE, self::TYPE_NUMERIC, $from, $to, $confidence, $source);
        }

        if ($from !== null) {
            return new self($key, self::OP_GTE, self::TYPE_NUMERIC, $from, null, $confidence, $source);
        }

        return new self($key, self::OP_LTE, self::TYPE_NUMERIC, $to, null, $confidence, $source);
    }

    public static function textEquals(
        string $key,
        string $value,
        float $confidence = 1.0,
        string $source = 'local'
    ): self {
        return new self($key, self::OP_EQUALS, self::TYPE_TEXT, $value, null, $confidence, $source);
    }

    /**
     * هل ثقة هذا الشرط تكفي لإقصاء نتائج، أم يقتصر أثره على الترجيح؟
     */
    public function isHard(?float $threshold = null): bool
    {
        $threshold ??= (float) config('search.understanding.filter_confidence_threshold', 0.80);

        return $this->confidence >= $threshold;
    }

    public function isNumeric(): bool
    {
        return $this->type === self::TYPE_NUMERIC;
    }

    /**
     * نسخة بثقة مختلفة — تُستخدم حين ترفع دلائل أخرى ثقة شرط مستخرج سلفاً.
     */
    public function withConfidence(float $confidence): self
    {
        return new self(
            $this->key,
            $this->operator,
            $this->type,
            $this->value,
            $this->valueTo,
            max(0.0, min(1.0, $confidence)),
            $this->source,
        );
    }

    /**
     * بصمة مميِّزة تُستعمل لإزالة التكرار.
     */
    public function fingerprint(): string
    {
        return implode(':', [
            $this->key,
            $this->operator,
            (string) $this->value,
            (string) ($this->valueTo ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'operator' => $this->operator,
            'type' => $this->type,
            'value' => $this->value,
            'value_to' => $this->valueTo,
            'confidence' => round($this->confidence, 4),
            'source' => $this->source,
            'hard' => $this->isHard(),
        ];
    }
}
