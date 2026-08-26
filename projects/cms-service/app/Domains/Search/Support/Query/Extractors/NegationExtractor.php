<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query\Extractors;

use App\Domains\Search\Support\Lexicon\Lexicon;

/**
 * NegationExtractor — فصل ما يريده المستخدم عمّا يستثنيه.
 *
 * "ما بدي ايفون 14" استعلامان في واحد: أريد آيفون، ولا أريد 14.
 * بلا هذا الفصل يُعامَل "14" كمطلوب، فتتصدّر النتائجَ الأشياءُ التي
 * رفضها المستخدم صراحةً — وهو أسوأ من تجاهل النفي أصلاً.
 *
 * ─── لماذا نافذة محدودة لا "إلى آخر الجملة" ─────────────────────────
 *
 *   النفي في اللغة الطبيعية له مدى. "بدون كفر، بدي ايفون 15" — كلمة
 *   "بدون" تنفي "كفر" وحدها لا بقية الجملة. لهذا يحمل كل دالّ في المعجم
 *   عدد الكلمات التي يستهلكها، ويتوقف الاستهلاك عند أول دالّ آخر.
 *
 *   المدى المفتوح كان يقلب "بدون كفر بدي ايفون" إلى استثناء كل شيء،
 *   فيعيد صفر نتائج على استعلام مفهوم تماماً.
 */
final class NegationExtractor
{
    public function __construct(
        private readonly Lexicon $lexicon,
    ) {}

    /**
     * @param  string[]  $tokens  الوحدات المطبَّعة
     * @param  string[]  $scripts
     * @return array{include: string[], exclude: string[], hadNegation: bool}
     */
    public function extract(array $tokens, array $scripts): array
    {
        $cues = $this->lexicon->negationCues($scripts);

        if ($cues === [] || $tokens === []) {
            return ['include' => $tokens, 'exclude' => [], 'hadNegation' => false];
        }

        $cueWords = $this->indexCueFirstWords($cues);
        $stopwords = $this->lexicon->stopwords($scripts);

        $include = [];
        $exclude = [];
        $hadNegation = false;

        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $match = $this->matchCueAt($tokens, $i, $cues, $cueWords);

            if ($match === null) {
                $include[] = $tokens[$i];
                $i++;

                continue;
            }

            [$cueLength, $scope] = $match;
            $hadNegation = true;
            $i += $cueLength;

            $taken = 0;

            while ($i < $count && $taken < $scope) {
                /*
                 | دالّ نفي جديد ينهي مدى السابق: "بدون كفر بدون شاحن"
                 | استثناءان مستقلان، لا استثناء واحد يبتلع أربع كلمات.
                 */
                if ($this->matchCueAt($tokens, $i, $cues, $cueWords) !== null) {
                    break;
                }

                $exclude[] = $tokens[$i];

                /*
                 | كلمات الوقف تُستهلَك ولا تُحتسب في المدى.
                 |
                 | "without a case" و"without case" استثناء واحد لا
                 | اثنان: أداة التعريف ليست شيئاً يُستثنى. لو حُسبت
                 | لاستنفدت المدى وحدها فنجا "case" من الاستثناء —
                 | أو لاتّسع المدى تعويضاً فابتلع كلمة تالية مطلوبة،
                 | كما في "without charger gaming rtx" حيث كان
                 | "gaming" يسقط من المطلوب بلا سبب.
                 */
                if (! isset($stopwords[$tokens[$i]])) {
                    $taken++;
                }

                $i++;
            }
        }

        return [
            'include' => $include,
            'exclude' => array_values(array_unique($exclude)),
            'hadNegation' => $hadNegation,
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * محاولة مطابقة دالّ يبدأ عند هذا الموضع.
     *
     * الدوالّ مرتّبة من الأطول إلى الأقصر في المعجم، فأول مطابقة هي
     * الأطول — وهو المطلوب: "ما بدي" قبل "بدي".
     *
     * @param  string[]  $tokens
     * @param  array<string, int>  $cues
     * @param  array<string, string[]>  $cueWords
     * @return array{0:int, 1:int}|null [عدد كلمات الدالّ، مدى الاستثناء]
     */
    private function matchCueAt(array $tokens, int $position, array $cues, array $cueWords): ?array
    {
        $candidates = $cueWords[$tokens[$position]] ?? [];

        foreach ($candidates as $cue) {
            $words = preg_split('/\s+/u', $cue, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $length = count($words);

            if ($length === 0 || $position + $length > count($tokens)) {
                continue;
            }

            if (array_slice($tokens, $position, $length) === $words) {
                return [$length, max(1, (int) $cues[$cue])];
            }
        }

        return null;
    }

    /**
     * فهرسة الدوالّ بكلمتها الأولى.
     *
     * بدون هذا يُجرَّب كل دالّ عند كل موضع — تعقيد يتضاعف مع كل لغة
     * تُضاف. الفهرسة تقصر المحاولات على الدوالّ التي يمكن أن تطابق أصلاً،
     * مع الحفاظ على ترتيب الأطول-أولاً داخل كل مجموعة.
     *
     * @param  array<string, int>  $cues
     * @return array<string, string[]>
     */
    private function indexCueFirstWords(array $cues): array
    {
        $index = [];

        foreach (array_keys($cues) as $cue) {
            $words = preg_split('/\s+/u', $cue, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($words === []) {
                continue;
            }

            $index[$words[0]][] = $cue;
        }

        return $index;
    }
}
