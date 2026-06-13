<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * NegationExtractionTrait
 *
 * يُشارك CASE A/B/C negation logic بين:
 *   - ArabicQueryNormalizer
 *   - EnglishQueryNormalizer
 *
 * ما يُشارَك هنا:
 *   - applyNegationCases(): الـ CASE A/B/C logic نفسه
 *   - isWordBoundary(): word boundary check
 *   - isAlphanumericChar(): UTF-8-safe alphanumeric check
 *
 * ما لا يُشارَك (يبقى في كل normalizer):
 *   - قائمة الـ patterns (Arabic vs English)
 *   - splitWords() (separator مختلف)
 *   - ترجمة الكلمات (Arabic فقط)
 *   - filler words (مختلفة لكل لغة)
 *
 * Zero behavior change — فقط deduplication.
 */
trait NegationExtractionTrait
{
    /**
     * تطبيق CASE A/B/C logic بعد إيجاد الـ negation pattern.
     *
     * CASE A: beforeText !== ''
     *   "ايفون ما بدي 14" | "iphone not 14"
     *   → include = beforeText
     *   → exclude = afterWords[0..3]
     *
     * CASE B: beforeText = '' + afterWords يحتوي أرقام + غير أرقام
     *   "ما بدي ايفون 14" | "not iphone 14"
     *   → include = non-numeric words
     *   → exclude = numeric words
     *
     * CASE C: beforeText = '' + afterWords بدون أرقام
     *   "بدون سامسونج" | "without samsung"
     *   → include = ''
     *   → exclude = afterWords[0..3]
     *
     * @param  string   $beforeText  النص قبل الـ pattern
     * @param  string[] $afterWords  الكلمات بعد الـ pattern (مُقسَّمة مسبقاً)
     * @return array{0: string, 1: string[], 2: bool}
     *         [includeText, excludeWords, hadNegation=true]
     */
    protected function applyNegationCases(string $beforeText, array $afterWords): array
    {
        // ── CASE A ────────────────────────────────────────────────────
        if ($beforeText !== '') {
            return [
                $beforeText,
                array_slice($afterWords, 0, 4),
                true,
            ];
        }

        // ── CASE B or C ───────────────────────────────────────────────
        $productWords = array_values(array_filter($afterWords, fn($w) => ! is_numeric($w)));
        $numberWords  = array_values(array_filter($afterWords, fn($w) => is_numeric($w)));

        if (! empty($numberWords) && ! empty($productWords)) {
            // CASE B: منتج + رقم → include=منتج، exclude=رقم
            return [
                implode(' ', $productWords),
                $numberWords,
                true,
            ];
        }

        // CASE C: كلمات فقط أو أرقام فقط → كلها exclude
        return [
            '',
            array_slice($afterWords, 0, 4),
            true,
        ];
    }

    /**
     * فحص word boundary في موضع محدد — UTF-8 safe.
     *
     * يغطي: spaces, commas, periods, dashes, Arabic punctuation, etc.
     * لا يعتمد على space فقط — أي non-alphanumeric = boundary.
     *
     * @param string $text      النص الكامل
     * @param int    $pos       موضع بداية الـ token
     * @param int    $tokenLen  طول الـ token بالـ chars
     */
    protected function isWordBoundary(string $text, int $pos, int $tokenLen): bool
    {
        $textLen = mb_strlen($text, 'UTF-8');

        // فحص الحرف قبل الـ token
        if ($pos > 0) {
            $charBefore = mb_substr($text, $pos - 1, 1, 'UTF-8');
            if ($this->isAlphanumericChar($charBefore)) {
                return false;
            }
        }

        // فحص الحرف بعد الـ token
        $endPos = $pos + $tokenLen;
        if ($endPos < $textLen) {
            $charAfter = mb_substr($text, $endPos, 1, 'UTF-8');
            if ($this->isAlphanumericChar($charAfter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * هل الحرف alphanumeric؟ (Latin + Arabic + digits)
     * UTF-8 safe.
     */
    protected function isAlphanumericChar(string $char): bool
    {
        if (empty($char)) return false;
        if (ctype_alnum($char)) return true;

        $code = mb_ord($char, 'UTF-8');
        return $code >= 0x0600 && $code <= 0x06FF;
    }
}