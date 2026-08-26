<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query\Extractors;

use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Query\AttributeFilter;

/**
 * LexicalAttributeExtractor — الكلمات التي تصف سمة لا موضوعاً.
 *
 * "ايفون اسود" — "اسود" ليست كلمة نبحث عنها في النص، بل قيمة سمة.
 * الفرق عملي لا نظري: المحتوى غالباً مُدخَل بالإنجليزية ("Color: Black")،
 * فالبحث النصّي عن "اسود" لا يطابق شيئاً مهما بلغت جودة المُرتِّب.
 * تحويلها إلى color=black يطابق بغضّ النظر عن لغة إدخال المحتوى.
 *
 * الكلمة تُستهلَك دائماً هنا خلافاً للسنة: قيمة السمة، حين نعرفها،
 * لا تحتمل تفسيراً ثانياً في هذا السياق.
 */
final class LexicalAttributeExtractor
{
    public function __construct(
        private readonly Lexicon $lexicon,
    ) {}

    /**
     * @param  string[]  $tokens
     * @param  string[]  $scripts
     */
    public function extract(array $tokens, array $scripts): ExtractionResult
    {
        $vocabulary = $this->lexicon->attributes($scripts);

        if ($vocabulary === []) {
            return ExtractionResult::empty();
        }

        $filters = [];
        $consumed = [];

        foreach ($tokens as $index => $token) {
            $mapping = $vocabulary[$token] ?? null;

            if (! is_array($mapping)) {
                continue;
            }

            foreach ($mapping as $key => $value) {
                $filters[] = AttributeFilter::textEquals((string) $key, (string) $value, 0.85);
            }

            $consumed[] = $index;
        }

        return new ExtractionResult($filters, $consumed);
    }
}
