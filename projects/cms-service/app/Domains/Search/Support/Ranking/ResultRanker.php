<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Ranking;

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Support\Query\QueryPlan;

/**
 * ResultRanker — تركيب الدرجة النهائية.
 *
 * ترتيب العمليات هو المعنى:
 *
 *     صلة  = BM25F + مكافأة التجاور        ← ما يقرّر إن كان المستند جواباً
 *     إشارة = سلوك + حداثة + شروط + نيّة    ← ما يرجّح بين الأجوبة
 *     شخصي  = مضاعِف محدود                  ← ما يرجّح بين المتقاربين
 *
 *     النهائية = (صلة + إشارة) × (1 + شخصي)
 *
 * الجمع قبل الضرب مقصود: التخصيص يضاعف ما تجمّع من دليل موضوعي ولا
 * يضيف دليلاً من عنده. مستند بلا صلة يبقى بلا صلة مهما وافق ذوق
 * المستخدم — وهو ما يمنع فقاعة الترشيح من أن تبتلع البحث.
 */
final class ResultRanker
{
    public function __construct(
        private readonly Bm25fScorer $bm25,
        private readonly SignalScorer $signals,
        private readonly PersonalizationScorer $personalization,
    ) {}

    /**
     * ترتيب المرشَّحين تنازلياً بالدرجة النهائية.
     *
     * @param  array<int, object>  $rows
     * @param  array<int, array<string, array<int, array{value_text:?string, value_num:?float}>>>  $attributes
     *                                                                                                          سمات المستندات، بمفتاح entry_id
     * @param  array<int, array{term:string, age_days:float}>  $recentTerms
     * @return array<int, object>
     */
    public function rank(
        array $rows,
        QueryPlan $plan,
        CorpusStatistics $stats,
        UserPreferenceDTO $preference,
        array $attributes = [],
        array $recentTerms = []
    ): array {
        if ($rows === []) {
            return [];
        }

        $recentTerms = $this->excludeCurrentQuery($recentTerms, $plan);

        foreach ($rows as $row) {
            $entryId = (int) ($row->entry_id ?? 0);
            $rowAttributes = $attributes[$entryId] ?? [];

            $relevance = $this->bm25->score($plan, $row, $stats)
                + $this->bm25->phraseBonus($plan, $row);

            $signal = $this->signals->score($plan, $row, $rowAttributes);

            $row->relevance_score = round($relevance, 6);
            $row->signal_score = round($signal, 6);

            $row->final_score = round(
                $this->personalization->apply(
                    $relevance + $signal,
                    $row,
                    $preference,
                    $recentTerms
                ),
                6
            );
        }

        return $this->sortByScore($rows);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * صدى البحث الأخير، منزوعاً منه ما يطابق الاستعلام الحالي.
     *
     * ─── لماذا يُنزع ───────────────────────────────────────────────
     *
     * من بحث عن "pro" قبل دقيقة ثم بحث عنها الآن، تحمل مصطلحاته
     * الأخيرة كلمة "pro". فترفع إشارةُ الحداثة كلَّ نتيجة تحتوي
     * "pro" — وهي كل نتيجة في هذا الاستعلام بالتعريف.
     *
     * والأثر مزدوج الضرر: احتسابٌ ثانٍ لما حسبه BM25 أصلاً، وترجيحٌ
     * موحَّد على القائمة كلها لا يغيّر ترتيباً بل يبلغ بالمضاعِف سقفَه
     * فيمحو ما تبقّى من إشارات مميِّزة.
     *
     * قيمة الحداثة في المصطلحات التي بحث عنها المستخدم ولم يذكرها
     * الآن: هي ما يكشف سياقه المستمرّ، وهي وحدها ما يميّز نتيجة عن
     * أخرى داخل النتائج نفسها.
     *
     * @param  array<int, array{term:string, age_days:float}>  $recentTerms
     * @return array<int, array{term:string, age_days:float}>
     */
    private function excludeCurrentQuery(array $recentTerms, QueryPlan $plan): array
    {
        if ($recentTerms === []) {
            return [];
        }

        $current = array_flip($plan->allTerms());

        return array_values(array_filter(
            $recentTerms,
            static fn (array $entry): bool => ! isset($current[$entry['term']])
        ));
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    private function sortByScore(array $rows): array
    {
        /*
         | الترتيب بالمعرّف عند التعادل.
         |
         | usort في PHP مستقرّ منذ 8.0، لكن الاستقرار وحده يعني أن
         | ترتيب المتعادلين يتبع ترتيب وصولهما من قاعدة البيانات —
         | وهو غير مضمون بلا ORDER BY حاسم. الفاصل الصريح يجعل الصفحة
         | الثانية متّسقة مع الأولى بدل أن تكرّر عناصر أو تُسقطها.
         */
        usort($rows, static function (object $a, object $b): int {
            $comparison = $b->final_score <=> $a->final_score;

            return $comparison !== 0
                ? $comparison
                : ((int) ($a->entry_id ?? 0) <=> (int) ($b->entry_id ?? 0));
        });

        return $rows;
    }
}
