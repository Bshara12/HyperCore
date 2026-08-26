<?php

declare(strict_types=1);

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\SearchResultDTO;
use App\Domains\Search\DTOs\SearchResultItemDTO;
use App\Domains\Search\Repositories\Interfaces\SearchIndexQueryRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Services\QueryPlanRefiner;
use App\Domains\Search\Services\QueryRescueService;
use App\Domains\Search\Support\Query\QueryAnalyzer;
use App\Domains\Search\Support\Query\QueryPlan;
use App\Domains\Search\Support\Ranking\ResultRanker;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use App\Jobs\IncrementViewCountJob;
use Illuminate\Support\Facades\Log;

/**
 * SearchEntriesAction — تنسيق البحث.
 *
 * ─── المسار ─────────────────────────────────────────────────────────
 *
 *   1. الفهم    محلّي، حتمي، بلا شبكة
 *   2. الاسترجاع مرشَّحون من MySQL بنافذة تغطّي الصفحة المطلوبة
 *   3. الترتيب   BM25F + إشارات + تخصيص محدود
 *   4. الاحتياطي نموذج لغوي — عند العدم فقط، وبضمانات
 *
 * ─── ما تغيّر عن الإصدار السابق ─────────────────────────────────────
 *
 * كان المسار السابق يحمل سلّم احتياطيات متشعّباً داخل هذا الصنف: كشف
 * الهذر، ثم إصلاح تخطيط لوحة المفاتيح، ثم استدعاء النموذج، ولكلٍّ
 * شروطه وعتباته المتداخلة. وكان كل فرع منها يعيد تنفيذ البحث كاملاً،
 * فيصل الاستعلام الواحد إلى ثلاث جولات على قاعدة البيانات.
 *
 * والأدهى أن شرط الاحتياطي كان يشمل isNaturalLanguage، وهذه كانت
 * true لكل استعلام عربي بلا استثناء — فكان النموذج يُستدعى حتى حين
 * ينجح البحث المحلّي ويجد نتائج.
 *
 * هنا الشرط واحد وصريح: لا نتائج. وما عداه يُحسم في طبقة الفهم.
 */
class SearchEntriesAction
{
    public function __construct(
        private readonly QueryAnalyzer $analyzer,
        private readonly SearchIndexQueryRepositoryInterface $repository,
        private readonly ResultRanker $ranker,
        private readonly UserPreferenceAnalyzer $preferences,
        private readonly UserBehaviorRepositoryInterface $behavior,
        private readonly QueryRescueService $rescue,
        private readonly QueryPlanRefiner $refiner,
        private readonly LogSearchAction $logSearch,
    ) {}

    public function execute(SearchQueryDTO $dto): SearchResultDTO
    {
        $plan = $this->analyzer->analyze(
            $dto->keyword,
            $dto->dataTypeSlug,
            $dto->projectId,
            $dto->language,
        );

        if (! $plan->isExecutable() && ! $plan->isExclusionOnly()) {
            return $this->emptyResult($dto, $plan);
        }

        $outcome = $this->retrieve($plan, $dto);

        /*
         | ─── سلّم الإنقاذ ────────────────────────────────────────────
         |
         | درجتان، وشرط الصعود إليهما واحد: صفر نتائج.
         |
         |   ① إنقاذ محلّي  خطأ لوحة المفاتيح والخطأ المطبعي — حتمي
         |                   وبلا شبكة، ويغطّي أشيع سببَي الإخفاق.
         |
         |   ② احتياطي ذكي  لما عجز عنه الأول، وبكلفة شبكة.
         |
         | الترتيب مقصود: إرسال خطأ مطبعي إلى نموذج لغوي يدفع كلفة
         | وزمناً مقابل إجابة كان يمكن اشتقاقها يقيناً من مفردات
         | المشروع نفسها.
         */
        if ($outcome['total'] === 0) {
            $rescued = $this->rescueLocally($plan, $dto);

            if ($rescued !== null) {
                [$plan, $outcome] = $rescued;
            }
        }

        if ($outcome['total'] === 0) {
            $refined = $this->refiner->refine($plan, $dto->projectId, $dto->language);

            if ($refined !== null) {
                $retry = $this->retrieve($refined, $dto);

                if ($retry['total'] > 0) {
                    $plan = $refined;
                    $outcome = $retry;
                }
            }
        }

        $this->recordSearch($dto, $plan, $outcome['total']);
        $this->trackViews($outcome['items'], $dto->language);

        return $this->buildResult($dto, $plan, $outcome);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تجربة الخطط البديلة المحلّية، وقبول أوّل ما يجد نتائج.
     *
     * القبول مشروط بوجود نتائج فعلاً، لا بمعقولية الاقتراح. فمهما بدا
     * عكس تخطيط لوحة المفاتيح أو التصحيح الإملائي صائباً، خطّة لا تطابق
     * شيئاً في الفهرس لا تنفع المستخدم في شيء — والأسوأ أن قبولها يغيّر
     * الكلمة المعروضة له بلا مقابل.
     *
     * @return array{0: QueryPlan, 1: array{items: array<int, object>, total: int, relaxation: int}}|null
     */
    private function rescueLocally(QueryPlan $plan, SearchQueryDTO $dto): ?array
    {
        foreach ($this->rescue->candidates($plan, $dto->projectId, $dto->language) as $candidate) {
            $outcome = $this->retrieve($candidate['plan'], $dto);

            if ($outcome['total'] > 0) {
                Log::info('SearchEntriesAction: local rescue succeeded', [
                    'strategy' => $candidate['strategy'],
                    'original' => $plan->folded,
                    'rescued_terms' => $candidate['plan']->terms,
                    'total' => $outcome['total'],
                ]);

                return [$candidate['plan'], $outcome];
            }
        }

        return null;
    }

    /**
     * استرجاع وترتيب لخطة واحدة.
     *
     * @return array{items: array<int, object>, total: int, relaxation: int}
     */
    private function retrieve(QueryPlan $plan, SearchQueryDTO $dto): array
    {
        $candidates = $this->repository->fetchCandidates($plan, $dto);

        if ($candidates['rows'] === []) {
            return ['items' => [], 'total' => $candidates['total'], 'relaxation' => $candidates['relaxation']];
        }

        $window = $candidates['window'];

        /*
         | الترقيم العميق يتخطّى إعادة الترتيب.
         |
         | النافذة عندئذٍ صفحة واحدة بإزاحتها الحقيقية، فترتيبها في PHP
         | لا يعني شيئاً — ترتيب صفحة بمعزل عن سابقتها ولاحقتها يخلط
         | الحدود بينها بدل أن يحسّنها.
         */
        if (! $window->rerank) {
            return [
                'items' => $candidates['rows'],
                'total' => $candidates['total'],
                'relaxation' => $candidates['relaxation'],
            ];
        }

        $entryIds = array_map(
            static fn (object $row): int => (int) $row->entry_id,
            $candidates['rows']
        );

        $ranked = $this->ranker->rank(
            rows: $candidates['rows'],
            plan: $plan,
            stats: $this->repository->corpusStatistics($plan, $dto->projectId, $dto->language),
            preference: $this->preferences->analyze($dto->projectId, $dto->userId, $dto->sessionId),
            attributes: $this->repository->attributesFor($entryIds, $dto->projectId, $dto->language),
            recentTerms: $this->recentTerms($dto),
        );

        return [
            'items' => $window->slice($ranked, $dto->perPage),
            'total' => $candidates['total'],
            'relaxation' => $candidates['relaxation'],
        ];
    }

    /**
     * مصطلحات بحث المستخدم الأخيرة، بأعمارها بالأيام.
     *
     * العمر يُحسب ويُمرَّر موجباً صراحةً. الإصدار السابق كان يستدعي
     * now()->diffInDays($past) ويفترض قيمة موجبة، بينما تعيدها Carbon 3
     * موقَّعةً — فكانت سالبة، فينقلب الاضمحلال الأسّي نموّاً ويصير
     * أقدمُ اهتمامات المستخدم أثقلَها وزناً.
     *
     * @return array<int, array{term:string, age_days:float}>
     */
    private function recentTerms(SearchQueryDTO $dto): array
    {
        if ($dto->userId === null) {
            return [];
        }

        try {
            $history = $this->behavior->getRecentSearchTerms(
                $dto->projectId,
                $dto->userId,
                (int) config('search.ranking.personalization.history_days', 30)
            );
        } catch (\Throwable $e) {
            Log::warning('SearchEntriesAction: recent terms lookup failed', ['error' => $e->getMessage()]);

            return [];
        }

        return $history;
    }

    // ─── بناء الاستجابة ──────────────────────────────────────────────

    /**
     * @param  array{items: array<int, object>, total: int, relaxation: int}  $outcome
     */
    private function buildResult(SearchQueryDTO $dto, QueryPlan $plan, array $outcome): SearchResultDTO
    {
        $total = $outcome['total'];

        return new SearchResultDTO(
            keyword: $dto->keyword,
            total: $total,
            page: $dto->page,
            perPage: $dto->perPage,
            lastPage: $total > 0 ? (int) ceil($total / max(1, $dto->perPage)) : 1,
            items: array_map(
                fn (object $row): SearchResultItemDTO => $this->toItem($row, $plan),
                $outcome['items']
            ),
            aiEnhanced: str_starts_with($plan->source, 'ai'),
            aiQuery: str_starts_with($plan->source, 'ai') ? implode(' ', $plan->terms) : null,
            keyboardFixed: false,
            keyboardQuery: null,
        );
    }

    private function emptyResult(SearchQueryDTO $dto, QueryPlan $plan): SearchResultDTO
    {
        $this->recordSearch($dto, $plan, 0);

        return new SearchResultDTO(
            keyword: $dto->keyword,
            total: 0,
            page: $dto->page,
            perPage: $dto->perPage,
            lastPage: 1,
            items: [],
        );
    }

    private function toItem(object $row, QueryPlan $plan): SearchResultItemDTO
    {
        $highlightTerms = $plan->allTerms();

        return new SearchResultItemDTO(
            entryId: (int) $row->entry_id,
            dataTypeId: (int) $row->data_type_id,
            projectId: (int) $row->project_id,
            language: (string) $row->language,
            title: $this->highlight((string) ($row->title ?? ''), $highlightTerms),
            snippet: $this->highlight(
                $this->snippet((string) ($row->content ?? ''), $highlightTerms),
                $highlightTerms
            ),
            status: (string) $row->status,
            score: round((float) ($row->final_score ?? $row->retrieval_score ?? 0), 4),
            publishedAt: $row->published_at,
        );
    }

    /**
     * مقتطف حول أول مطابقة.
     *
     * المطابقة تُبحث في الصورة المطبَّعة بينما يُقتطع النصّ الأصلي:
     * البحث في الأصل يعني أن "قهوه" لن تجد "قَهْوَة"، والاقتطاع من
     * المطبَّع يعني عرض نصّ منزوع التشكيل على القارئ.
     *
     * @param  string[]  $terms
     */
    private function snippet(string $content, array $terms, int $before = 60, int $after = 120): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ) ?? '');

        if ($plain === '') {
            return '';
        }

        $position = $this->firstMatch($plain, $terms);

        if ($position === null) {
            return mb_strlen($plain, 'UTF-8') <= 180
                ? $plain
                : mb_substr($plain, 0, 180, 'UTF-8').'…';
        }

        $length = mb_strlen($plain, 'UTF-8');
        $start = max(0, $position - $before);
        $end = min($length, $position + $after);

        return ($start > 0 ? '…' : '')
            .trim(mb_substr($plain, $start, $end - $start, 'UTF-8'))
            .($end < $length ? '…' : '');
    }

    /**
     * @param  string[]  $terms
     */
    private function firstMatch(string $text, array $terms): ?int
    {
        $earliest = null;

        foreach ($terms as $term) {
            if (mb_strlen($term, 'UTF-8') < 2) {
                continue;
            }

            $position = mb_stripos($text, $term, 0, 'UTF-8');

            if ($position !== false && ($earliest === null || $position < $earliest)) {
                $earliest = $position;
            }
        }

        return $earliest;
    }

    /**
     * تظليل المصطلحات المطابِقة.
     *
     * المصطلحات مرتّبة من الأطول إلى الأقصر: تظليل "pro" قبل "promax"
     * يقطع الأطول فينتج تظليل متداخل ومكسور.
     *
     * @param  string[]  $terms
     */
    private function highlight(string $text, array $terms): string
    {
        if ($text === '' || $terms === []) {
            return $text;
        }

        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($terms as $term) {
            if (mb_strlen($term, 'UTF-8') < 2) {
                continue;
            }

            $result = preg_replace(
                '/(?<!\*\*)('.preg_quote($term, '/').')(?!\*\*)/iu',
                '**$1**',
                $text
            );

            $text = $result ?? $text;
        }

        return $text;
    }

    // ─── آثار جانبية ─────────────────────────────────────────────────

    private function recordSearch(SearchQueryDTO $dto, QueryPlan $plan, int $total): void
    {
        try {
            $this->logSearch->execute(new LogSearchDTO(
                projectId: $dto->projectId,
                keyword: $dto->keyword,
                language: $dto->language,
                resultsCount: $total,
                detectedIntent: $plan->intent['intent'],
                intentConfidence: $plan->intent['confidence'],
                userId: $dto->userId,
                sessionId: $dto->sessionId,
            ));
        } catch (\Throwable $e) {
            // تسجيل البحث تحليلات، وفشلها لا يبرّر إفشال البحث نفسه.
            Log::warning('SearchEntriesAction: search logging failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, object>  $rows
     */
    private function trackViews(array $rows, string $language): void
    {
        if ($rows === []) {
            return;
        }

        $entryIds = array_values(array_unique(array_map(
            static fn (object $row): int => (int) $row->entry_id,
            $rows
        )));

        IncrementViewCountJob::dispatch($entryIds, $language)->onQueue('search-tracking');
    }
}
