<?php

namespace App\Domains\Search\Support;

final class ProcessedKeyword
{
    public function __construct(
        public readonly string $original,
        public readonly string $booleanQuery,
        public readonly array  $cleanWords,
        public readonly string $primaryWord,
        public readonly array  $relaxedQueries,
        public readonly array  $expandedGroups,

        /**
         * نتيجة كشف النية
         *
         * [
         *   'intent'     => 'product',   // product | article | service | general
         *   'confidence' => 0.8,         // 0.0 → 1.0
         *   'scores'     => [            // raw scores لكل نية
         *     'product' => 0.8,
         *     'article' => 0.1,
         *     'service' => 0.1,
         *   ]
         * ]
         */
        public readonly array  $intent,
    ) {}
}