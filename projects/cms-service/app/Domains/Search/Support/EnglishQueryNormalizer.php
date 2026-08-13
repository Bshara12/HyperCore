<?php

namespace App\Domains\Search\Support;

class EnglishQueryNormalizer
{
    use NegationExtractionTrait;

    // FIX: أضفنا 'dont' و "don't" بأشكالهم المختلفة
    private const NEGATION_PATTERNS = [
        'not including' => 2,
        'other than'    => 2,
        'aside from'    => 2,
        'apart from'    => 2,
        'do not need'   => 3,
        'do not want'   => 3,
        "don't need"    => 2,
        "don't want"    => 2,
        "dont need"     => 2,
        "dont want"     => 2,
        'excluding'     => 1,
        'without'       => 1,
        'except'        => 1,
        'exclude'       => 1,
        'minus'         => 1,
        'not'           => 1,
        'no'            => 1,
    ];

    private const FILLER_WORDS = [
        'want','need','looking','find','show','please',
        'give','tell','help','get','searching','search',
        'for','me','a','an','the','some','any','i','im',
        'id','like','just','only',
    ];

    public function normalize(string $query): array
    {
        // FIX: نُنظّف الـ apostrophes قبل الفحص حتى "don't" = "dont"
        $lower = mb_strtolower(trim($query), 'UTF-8');
        $lower = str_replace("'", '', $lower); // don't → dont

        [$includeText, $excludeWords, $hadNegation] = $this->extractNegationsBase(
            text:             $lower,
            negationPatterns: self::NEGATION_PATTERNS,
            splitWords:       fn(string $t) => $this->splitWords($t),
            wordBoundaryCheck: fn(string $text, int $pos, string $pattern)
                => $this->englishWordBoundaryCheck($text, $pos, $pattern)
        );

        $includeWords    = $this->splitWords($includeText);
        $filteredInclude = $this->removeFillerWords($includeWords, self::FILLER_WORDS);
        $filteredInclude = array_values(array_filter(
            $filteredInclude,
            fn($w) => mb_strlen($w, 'UTF-8') >= 2
        ));

        $excludeTerms = $this->buildExcludeTermsList(
            rawWords:       $excludeWords,
            fillerWords:    self::FILLER_WORDS,
            translationMap: []
        );

        // isNaturalLanguage: negation OR long conversational query
        $isNaturalLanguage = $hadNegation || count($includeWords) >= 5;

        return [
            'normalized'        => implode(' ', $filteredInclude),
            'excludeTerms'      => $excludeTerms,
            'isNaturalLanguage' => $isNaturalLanguage,
            'cleanWords'        => $filteredInclude,
        ];
    }

    public function hasNegation(string $query): bool
    {
        // FIX: نُنظّف apostrophes قبل الفحص
        $lower    = mb_strtolower(trim($query), 'UTF-8');
        $lower    = str_replace("'", '', $lower);
        $patterns = array_keys(self::NEGATION_PATTERNS);
        usort($patterns, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach ($patterns as $pattern) {
            $pos = mb_strpos($lower, $pattern, 0, 'UTF-8');
            if ($pos === false) continue;
            if ($this->englishWordBoundaryCheck($lower, $pos, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function splitWords(string $text): array
    {
        if (empty(trim($text))) return [];
        $words = preg_split('/[\s\-_,\.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words, fn($w) => mb_strlen(trim($w), 'UTF-8') >= 1));
    }
}