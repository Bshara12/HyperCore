<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query\Extractors;

use App\Domains\Search\Support\Query\AttributeFilter;

/**
 * ExtractionResult — ما استخرجه مستخرِج واحد، وما استهلكه من الاستعلام.
 *
 * `consumed` هو نصف المسألة المهمل عادةً. حين نحوّل "2020" إلى شرط
 * قاطع على سنة الإصدار، يجب أن تختفي الكلمة من حساب الصلة النصّية:
 * وإلا احتُسبت مرّتين — مرّة كشرط ومرّة كمصطلح — فتتقدّم مستندات تذكر
 * "2020" في متنها على مستندات صدرت فعلاً في 2020.
 *
 * لكن الاستهلاك مشروط بالثقة. الشرط المرجِّح (لا القاطع) يُبقي كلمته
 * مصطلحاً، لأننا لسنا واثقين أنها زمن أصلاً وقد تكون اسم موديل.
 */
final readonly class ExtractionResult
{
    /**
     * @param  AttributeFilter[]  $filters
     * @param  int[]  $consumed  فهارس الوحدات التي تحوّلت إلى شروط قاطعة
     */
    public function __construct(
        public array $filters = [],
        public array $consumed = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->filters === [] && $this->consumed === [];
    }

    public function merge(self $other): self
    {
        return new self(
            filters: [...$this->filters, ...$other->filters],
            consumed: array_values(array_unique([...$this->consumed, ...$other->consumed])),
        );
    }
}
