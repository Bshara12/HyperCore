<?php

namespace App\Domains\Search\Support;

final class ProcessedKeyword
{
    public function __construct(
        public readonly string $original,
        public readonly string $booleanQuery,
        public readonly array $cleanWords,
        public readonly string $primaryWord,
        public readonly array $relaxedQueries,
        public readonly array $expandedGroups,     // من SynonymProvider (static map)
        public readonly array $intent,

        /*
         * إضافة جديدة: المجموعات بعد DB synonym expansion
         *
         * الفرق بين expandedGroups و dbExpandedGroups:
         *   expandedGroups   → من SynonymProvider (static PHP map)
         *   dbExpandedGroups → من synonym_suggestions table (dynamic, approved)
         *
         * مثال dbExpandedGroups:
         *   [
         *     "cost"   => ["cost", "price", "fee"],
         *     "iphone" => ["iphone"],             ← لا مرادفات معتمدة
         *   ]
         */
        public readonly array $dbExpandedGroups = [],  // ← جديد
        public readonly bool $hadDbExpansion = false, // ← جديد
    ) {}
}
