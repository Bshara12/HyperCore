<?php

namespace App\Domains\Search\Support;

/**
 * NegationExtractionTrait — لا تعديل على المنطق
 * فقط تأكد من وجوده كما هو
 */
trait NegationExtractionTrait
{
    protected function extractNegationsBase(
        string   $text,
        array    $negationPatterns,
        callable $splitWords,
        ?callable $wordBoundaryCheck = null
    ): array {
        uksort($negationPatterns, fn($a, $b) =>
            mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8')
        );

        foreach (array_keys($negationPatterns) as $pattern) {
            $pos = mb_strpos($text, $pattern, 0, 'UTF-8');
            if ($pos === false) continue;

            if ($wordBoundaryCheck !== null && ! $wordBoundaryCheck($text, $pos, $pattern)) {
                continue;
            }

            $beforeText  = trim(mb_substr($text, 0, $pos, 'UTF-8'));
            $afterOffset = $pos + mb_strlen($pattern, 'UTF-8');
            $afterText   = trim(mb_substr($text, $afterOffset, null, 'UTF-8'));
            $afterWords  = $splitWords($afterText);

            if ($beforeText !== '') {
                $excludeWords = array_slice($afterWords, 0, 4);
                return [$beforeText, $excludeWords, true];
            }

            $productWords = [];
            $numberWords  = [];

            foreach ($afterWords as $word) {
                if (is_numeric($word)) {
                    $numberWords[] = $word;
                } else {
                    $productWords[] = $word;
                }
            }

            if (! empty($numberWords) && ! empty($productWords)) {
                return [implode(' ', $productWords), $numberWords, true];
            }

            return ['', array_slice($afterWords, 0, 4), true];
        }

        return [$text, [], false];
    }

    protected function englishWordBoundaryCheck(string $text, int $pos, string $pattern): bool
    {
        if ($pos > 0) {
            $charBefore = mb_substr($text, $pos - 1, 1, 'UTF-8');
            if ($charBefore !== ' ') return false;
        }

        $patternLen = mb_strlen($pattern, 'UTF-8');
        $charAfter  = mb_substr($text, $pos + $patternLen, 1, 'UTF-8');

        if ($charAfter !== '' && $charAfter !== ' ') return false;

        return true;
    }

    protected function arabicWordBoundaryCheck(string $text, int $pos, string $pattern): bool
    {
        if ($pos > 0) {
            $charBefore = mb_substr($text, $pos - 1, 1, 'UTF-8');
            if ($charBefore !== ' ' && $charBefore !== '،') return false;
        }
        return true;
    }

    protected function removeFillerWords(array $words, array $fillerWords): array
    {
        $fillers = array_flip($fillerWords);
        return array_values(array_filter(
            $words,
            fn($w) => ! isset($fillers[$w])
        ));
    }

    protected function buildExcludeTermsList(
        array $rawWords,
        array $fillerWords,
        array $translationMap = []
    ): array {
        $fillers = array_flip($fillerWords);
        $result  = [];

        foreach ($rawWords as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 1) continue;
            if (isset($fillers[$word])) continue;

            if (is_numeric($word)) {
                $result[] = $word;
                continue;
            }

            $translated = $translationMap[$word] ?? $word;
            if (mb_strlen($translated, 'UTF-8') >= 2) {
                $result[] = $translated;
            }
        }

        return array_values(array_unique($result));
    }
}