<?php

declare(strict_types=1);

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\Repositories\Interfaces\SearchIndexQueryRepositoryInterface;
use App\Domains\Search\Support\Query\AttributeFilter;
use App\Domains\Search\Support\Query\QueryPlan;
use App\Domains\Search\Support\Ranking\CorpusStatistics;
use App\Domains\Search\Support\Retrieval\BooleanQueryBuilder;
use App\Domains\Search\Support\Retrieval\CandidateWindow;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * استرجاع المرشَّحين من الفهرس.
 *
 * ─── العلل التي يعالجها ─────────────────────────────────────────────
 *
 * 1. الترقيم المكسور
 *
 *    كان الاستعلام السابق ينتهي بـ:
 *
 *        ORDER BY fulltext_score DESC LIMIT ? OFFSET ?
 *        bindings: [..., $fetchLimit, 0]
 *
 *    الإزاحة مثبَّتة على الصفر دائماً. ثم تُرتَّب المئة صفّ المسحوبة
 *    في PHP وتُقتطع الصفحة منها بـ array_slice. فما دامت الصفحة ضمن
 *    المئة الأولى يعمل الأمر، وما إن تتجاوزها حتى تعود صفحات فارغة
 *    بينما يعلن total وجود مئات النتائج. أي أن كل بحث بأكثر من
 *    سبع صفحات كان مكسوراً بصمت.
 *
 *    هنا تُحسب النافذة من الصفحة المطلوبة فتغطّيها دائماً، وللترقيم
 *    العميق مسار صريح يتخلّى عن إعادة الترتيب بدل أن يعيد فراغاً.
 *
 * 2. استدعاء MATCH() مرّتين
 *
 *    كان الاستعلام يستدعي MATCH مرّة بـ NATURAL LANGUAGE للترتيب
 *    ومرّة بـ BOOLEAN للتصفية — بحثان كاملان في الفهرس لكل صفحة.
 *    وبما أن الترتيب النهائي صار BM25F في PHP، لم يعد استدعاء
 *    الترتيب لازماً أصلاً.
 *
 * 3. الحقول المخصَّصة خارج البحث
 *
 *    عمود meta كان مخزَّناً وغير مفهرس. الشروط البنيوية هنا تُنفَّذ
 *    على جدول سمات مفهرس عبر EXISTS، فتُقصي في SQL لا في PHP —
 *    والفرق أن الإقصاء في PHP يقع بعد اقتطاع النافذة، فيعيد صفحات
 *    منقوصة كلما أُقصي منها شيء.
 */
class EloquentSearchIndexQueryRepository implements SearchIndexQueryRepositoryInterface
{
    /**
     * مفاتيح السمات المتاحة، بمفتاح "مشروع:لغة".
     *
     * @var array<string, array<string, true>>
     */
    private array $attributeKeys = [];

    public function __construct(
        private readonly BooleanQueryBuilder $booleanBuilder,
    ) {}

    /**
     * جلب المرشَّحين لخطة.
     *
     * @return array{rows: array<int, object>, total: int, relaxation: int, window: CandidateWindow}
     */
    public function fetchCandidates(QueryPlan $plan, SearchQueryDTO $dto): array
    {
        $window = CandidateWindow::forPage($dto->page, $dto->perPage);
        $queries = $this->booleanBuilder->build($plan);

        // خطة بلا مصطلحات: استثناءات أو شروط بنيوية وحدها.
        if ($queries === []) {
            return $this->fetchWithoutFulltext($plan, $dto, $window);
        }

        foreach ($queries as $relaxation => $booleanQuery) {
            $result = $this->execute($plan, $dto, $window, $booleanQuery);

            if ($result['total'] > 0) {
                return [...$result, 'relaxation' => $relaxation, 'window' => $window];
            }
        }

        return ['rows' => [], 'total' => 0, 'relaxation' => -1, 'window' => $window];
    }

    /**
     * سمات مجموعة مستندات، مجمَّعة للترجيح في PHP.
     *
     * تُجلب في استعلام واحد لكل صفحة لا استعلام لكل مستند: الثاني
     * يعني مئة رحلة ذهاب وإياب إلى قاعدة البيانات في كل بحث.
     *
     * @param  int[]  $entryIds
     * @return array<int, array<string, array<int, array{value_text:?string, value_num:?float}>>>
     */
    public function attributesFor(array $entryIds, int $projectId, string $language): array
    {
        if ($entryIds === []) {
            return [];
        }

        $rows = DB::table('search_index_attributes')
            ->select('entry_id', 'attr_key', 'value_text', 'value_num')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->whereIn('entry_id', array_values(array_unique($entryIds)))
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->entry_id][(string) $row->attr_key][] = [
                'value_text' => $row->value_text === null ? null : (string) $row->value_text,
                'value_num' => $row->value_num === null ? null : (float) $row->value_num,
            ];
        }

        return $grouped;
    }

    /**
     * إحصاءات المتن ومصطلحات الخطة، في استعلامين.
     */
    public function corpusStatistics(QueryPlan $plan, int $projectId, string $language): CorpusStatistics
    {
        $corpus = DB::table('search_corpus_stats')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->first();

        if ($corpus === null || (int) $corpus->doc_count === 0) {
            return CorpusStatistics::fallback();
        }

        $terms = $plan->allTerms();
        $frequencies = [];

        if ($terms !== []) {
            $rows = DB::table('search_term_stats')
                ->select('term', 'doc_freq')
                ->where('project_id', $projectId)
                ->where('language', $language)
                ->whereIn('term', $terms)
                ->get();

            foreach ($rows as $row) {
                $frequencies[(string) $row->term] = (int) $row->doc_freq;
            }
        }

        return new CorpusStatistics(
            documentCount: (int) $corpus->doc_count,
            avgTitleTerms: (float) $corpus->avg_title_terms,
            avgContentTerms: (float) $corpus->avg_content_terms,
            avgMetaTerms: (float) $corpus->avg_meta_terms,
            documentFrequencies: $frequencies,
        );
    }

    public function incrementClickCount(int $entryId, string $language): void
    {
        DB::table('search_indices')
            ->where('entry_id', $entryId)
            ->where('language', $language)
            ->increment('click_count');
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array{rows: array<int, object>, total: int}
     */
    private function execute(
        QueryPlan $plan,
        SearchQueryDTO $dto,
        CandidateWindow $window,
        string $booleanQuery
    ): array {
        $matchColumns = $this->matchColumns($plan);

        $base = $this->baseQuery($plan, $dto)
            ->whereRaw("MATCH({$matchColumns}) AGAINST (? IN BOOLEAN MODE)", [$booleanQuery]);

        $total = (int) (clone $base)->count();

        if ($total === 0) {
            return ['rows' => [], 'total' => 0];
        }

        /*
         | ترتيب أوّلي بدرجة MySQL لاختيار النافذة فقط.
         |
         | الدرجة النهائية BM25F في PHP، لكن اختيار المرشَّحين يحتاج
         | ترتيباً ما داخل SQL: بلا ORDER BY تعيد قاعدة البيانات أول
         | ما تجده، فقد تكون النافذة كلها من ذيل النتائج ولا يظهر
         | المستند الأوثق صلةً أصلاً.
         */
        $rows = (clone $base)
            ->selectRaw("MATCH({$matchColumns}) AGAINST (? IN BOOLEAN MODE) AS retrieval_score", [$booleanQuery])
            ->orderByDesc('retrieval_score')
            ->orderByDesc('si.entry_id')
            ->limit($window->size)
            ->offset($window->sqlOffset)
            ->get()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * مسار الخطط غير النصّية: استثناءات أو شروط بنيوية وحدها.
     *
     * "ايفون بدون كفر" بعد إزالة المصطلحات المستثناة قد لا يبقى فيه
     * ما يُبحث عنه نصّياً. وMySQL FULLTEXT لا يقبل استعلاماً بلا
     * مصطلح موجب، فيلزم مسار لا يمرّ به.
     *
     * @return array{rows: array<int, object>, total: int, relaxation: int, window: CandidateWindow}
     */
    private function fetchWithoutFulltext(
        QueryPlan $plan,
        SearchQueryDTO $dto,
        CandidateWindow $window
    ): array {
        if ($plan->hardFilters() === [] && $plan->mustNot === []) {
            return ['rows' => [], 'total' => 0, 'relaxation' => -1, 'window' => $window];
        }

        $base = $this->baseQuery($plan, $dto);
        $total = (int) (clone $base)->count();

        if ($total === 0) {
            return ['rows' => [], 'total' => 0, 'relaxation' => -1, 'window' => $window];
        }

        $rows = (clone $base)
            ->orderByDesc('si.popularity_score')
            ->orderByDesc('si.published_at')
            ->orderByDesc('si.entry_id')
            ->limit($window->size)
            ->offset($window->sqlOffset)
            ->get()
            ->all();

        return ['rows' => $rows, 'total' => $total, 'relaxation' => 0, 'window' => $window];
    }

    /**
     * الاستعلام الأساسي: النطاق، الفلاتر البنيوية، الاستثناءات.
     */
    private function baseQuery(QueryPlan $plan, SearchQueryDTO $dto): Builder
    {
        $query = DB::table('search_indices as si')
            ->select([
                'si.entry_id', 'si.data_type_id', 'si.data_type_slug', 'si.project_id',
                'si.language', 'si.script', 'si.title', 'si.content',
                'si.title_fold', 'si.content_fold', 'si.meta_fold',
                'si.title_terms', 'si.content_terms', 'si.meta_terms',
                'si.status', 'si.published_at',
                'si.click_count', 'si.view_count', 'si.popularity_score',
            ])
            ->where('si.project_id', $dto->projectId)
            ->where('si.language', $dto->language)
            ->where('si.status', 'published');

        if ($dto->dataTypeSlug !== null) {
            $query->where('si.data_type_slug', $dto->dataTypeSlug);
        }

        foreach ($this->enforceableFilters($plan, $dto) as $filter) {
            $this->applyAttributeFilter($query, $filter);
        }

        $this->applyExclusions($query, $plan);

        return $query;
    }

    /**
     * الشروط القاطعة التي يملك المشروع بياناتٍ لتنفيذها.
     *
     * ─── لماذا لا تُنفَّذ كلها ──────────────────────────────────────
     *
     * الشرط القاطع على سمة لا يملكها المشروع أصلاً يضمن صفر نتائج،
     * مهما كان الاستعلام موفَّقاً. فمن يبحث عن "الايفون يلي نزل بال
     * 2022" في متجر لم يُدخِل صاحبه سنة إصدار لأي منتج، يستحقّ أن يرى
     * الآيفونات — لا صفحة فارغة عن شرط لا يمكن لأي مستند أن يحقّقه.
     *
     * الغياب هنا نقص بيانات لا نفي: أن يخلو المشروع من حقل "السنة" لا
     * يعني أن منتجاته لم تصدر في سنة، بل أن أحداً لم يُدخِل السنة. أمّا
     * لو كان الحقل موجوداً ولم تطابقه أي قيمة، فذلك نفيٌ حقيقي يُحترم.
     *
     * الشرط المُستبعَد لا يضيع: يعود إلى SignalScorer كمرجِّح، فيتقدّم
     * من يطابقه إن وُجد ولا يُقصى من لا يطابقه.
     *
     * @return AttributeFilter[]
     */
    private function enforceableFilters(QueryPlan $plan, SearchQueryDTO $dto): array
    {
        $hard = $plan->hardFilters();

        if ($hard === []) {
            return [];
        }

        $available = $this->availableAttributeKeys($dto->projectId, $dto->language);

        return array_values(array_filter(
            $hard,
            static fn (AttributeFilter $f): bool => isset($available[$f->key])
        ));
    }

    /**
     * مفاتيح السمات الموجودة فعلاً في مشروع ولغة.
     *
     * تُقرأ مرّة لكل طلب وتُحتفظ داخل النسخة: القائمة قصيرة وتتغيّر
     * بمعدّل تغيّر بنية المحتوى لا بمعدّل البحث.
     *
     * @return array<string, true>
     */
    private function availableAttributeKeys(int $projectId, string $language): array
    {
        $cacheKey = $projectId.':'.$language;

        if (isset($this->attributeKeys[$cacheKey])) {
            return $this->attributeKeys[$cacheKey];
        }

        $keys = DB::table('search_index_attributes')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->distinct()
            ->pluck('attr_key')
            ->all();

        return $this->attributeKeys[$cacheKey] = array_fill_keys($keys, true);
    }

    /**
     * شرط بنيوي، منفَّذاً كـ EXISTS على جدول السمات.
     *
     * EXISTS لا JOIN: الربط يضاعف الصفوف حين يحمل المستند أكثر من
     * قيمة للسمة نفسها، فيفسد العدّ ويكرّر النتائج. وEXISTS يتوقّف
     * عند أول مطابقة فيستفيد من الفهرس المركَّب كاملاً.
     */
    private function applyAttributeFilter(Builder $query, AttributeFilter $filter): void
    {
        $query->whereExists(function ($sub) use ($filter) {
            $sub->select(DB::raw(1))
                ->from('search_index_attributes as sa')
                ->whereColumn('sa.entry_id', 'si.entry_id')
                ->whereColumn('sa.language', 'si.language')
                ->where('sa.attr_key', $filter->key);

            if (! $filter->isNumeric()) {
                $sub->where('sa.value_text', (string) $filter->value);

                return;
            }

            $value = (float) $filter->value;

            match ($filter->operator) {
                AttributeFilter::OP_GTE => $sub->where('sa.value_num', '>=', $value),
                AttributeFilter::OP_LTE => $sub->where('sa.value_num', '<=', $value),
                AttributeFilter::OP_RANGE => $sub->whereBetween('sa.value_num', [$value, (float) $filter->valueTo]),
                default => $sub->where('sa.value_num', $value),
            };
        });
    }

    /**
     * الاستثناءات.
     *
     * تُنفَّذ بـ NOT LIKE على الأعمدة المطبَّعة لا بمعامل "-" في
     * BOOLEAN MODE وحده. السبب أن "-" في MySQL يستثني المستند من
     * المطابقة لكنه لا يعمل إن كان التعبير كلّه سالباً، ولا يُطبَّق
     * في المسار غير النصّي أصلاً. التنفيذ هنا يجعل الاستثناء يسري
     * في كل المسارات بالسلوك نفسه.
     */
    private function applyExclusions(Builder $query, QueryPlan $plan): void
    {
        foreach ($plan->mustNot as $term) {
            $escaped = addcslashes((string) $term, '%_\\');

            $query->where(function ($group) use ($escaped) {
                $group->where(DB::raw("COALESCE(si.title_fold,'')"), 'NOT LIKE', "%{$escaped}%")
                    ->where(DB::raw("COALESCE(si.content_fold,'')"), 'NOT LIKE', "%{$escaped}%")
                    ->where(DB::raw("COALESCE(si.meta_fold,'')"), 'NOT LIKE', "%{$escaped}%");
            });
        }
    }

    /**
     * أعمدة MATCH: الفهرس المستهدَف.
     *
     * الـ parser خاصية للفهرس لا للاستعلام، فاختيار الفهرس يتم
     * باختيار أعمدته. استعلام يحتوي صينية أو يابانية أو تايلندية
     * يذهب إلى فهرس الـ ngram، وما عداه إلى الفهرس الأساسي.
     */
    private function matchColumns(QueryPlan $plan): string
    {
        return $plan->needsNgram
            ? 'si.ngram_text'
            : 'si.title_fold, si.content_fold, si.meta_fold';
    }
}
