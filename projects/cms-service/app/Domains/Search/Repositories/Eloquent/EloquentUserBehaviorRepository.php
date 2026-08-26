<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\LogClickDTO;
use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\Models\UserClickLog;
use App\Domains\Search\Models\UserSearchLog;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EloquentUserBehaviorRepository implements UserBehaviorRepositoryInterface
{
    public function logSearch(LogSearchDTO $dto): int
    {
        $log = UserSearchLog::create([
            'user_id' => $dto->userId,
            'project_id' => $dto->projectId,
            'keyword' => $dto->keyword,
            'language' => $dto->language,
            'detected_intent' => $dto->detectedIntent,
            'intent_confidence' => $dto->intentConfidence,
            'results_count' => $dto->resultsCount,
            'session_id' => $dto->sessionId,
            'searched_at' => now(),
        ]);

        return $log->id;
    }

    public function logClick(LogClickDTO $dto): void
    {
        UserClickLog::create([
            'user_id' => $dto->userId,
            'project_id' => $dto->projectId,
            'search_log_id' => $dto->searchLogId,
            'entry_id' => $dto->entryId,
            'data_type_id' => $dto->dataTypeId,
            'result_position' => $dto->resultPosition,
            'session_id' => $dto->sessionId,
            'clicked_at' => now(),
        ]);
    }

    public function getClickCountsByDataType(
        int $projectId,
        int $userId,
        int $days = 30
    ): array {
        $rows = DB::table('user_click_logs')
            ->select('data_type_id', DB::raw('COUNT(*) as click_count'))
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->where('clicked_at', '>=', now()->subDays($days))
            ->groupBy('data_type_id')
            ->get();

        return $rows->pluck('click_count', 'data_type_id')->toArray();
    }

    public function getClickCountsByDataTypeForSession(
        int $projectId,
        string $sessionId,
        int $days = 30
    ): array {
        $rows = DB::table('user_click_logs')
            ->select('data_type_id', DB::raw('COUNT(*) as click_count'))
            ->where('project_id', $projectId)
            ->where('session_id', $sessionId)
            ->where('clicked_at', '>=', now()->subDays($days))
            ->groupBy('data_type_id')
            ->get();

        return $rows->pluck('click_count', 'data_type_id')->toArray();
    }

    /**
     * نصّ المداخل المنقور عليها — العناوين وحدها.
     *
     * ─── لماذا العنوان دون المتن ───────────────────────────────────
     *
     * كان الاستعلام يضمّ content_fold إلى title_fold، فيُبنى ملفُ
     * تفضيل المستخدم من آلاف كلمات المتن. والمتن نثرٌ عام: كلماته
     * مشتركة بين كل المستندات تقريباً، فتتقارب ملفات كل المستخدمين
     * مهما تباعدت اهتماماتهم.
     *
     * وقد ظهر الأثر في القياس: مستخدمان بذوقين متعاكسين تماماً —
     * أحدهما ينقر الهواتف والآخر أدوات المطبخ — حصلا على المضاعِف
     * الأقصى نفسه في كل استعلام، لأن مفردات متن منتجاتهما تتقاطع في
     * كلمات عامة كثيرة.
     *
     * العنوان وحده هو ما يصف المستند فعلاً، وهو أيضاً ما يُطابَق عليه
     * في PersonalizationScorer — فتوحيد الطرفين شرط لعمل الإشارة.
     */
    public function getClickedEntryTexts(
        int $projectId,
        int $userId,
        int $days = 30,
        int $limit = 100
    ): array {
        return $this->clickedEntryTexts(
            fn ($query) => $query->where('ucl.user_id', $userId),
            $projectId,
            $days,
            $limit
        );
    }

    public function getClickedEntryTextsForSession(
        int $projectId,
        string $sessionId,
        int $days = 30,
        int $limit = 100
    ): array {
        return $this->clickedEntryTexts(
            fn ($query) => $query->where('ucl.session_id', $sessionId),
            $projectId,
            $days,
            $limit
        );
    }

    /**
     * عناوين المداخل المنقور عليها، نصٌّ واحد لكل نقرة.
     *
     * ─── لماذا يلزم قيد اللغة ──────────────────────────────────────
     *
     * المدخل الواحد مفهرس مرّة لكل لغة يدعمها المشروع، والربط على
     * entry_id وحده يطابقها كلها. فالنقرة الواحدة تُنتج صفّاً لكل لغة،
     * ويتعلّم المستخدم مفرداتِ لغات لم يبحث بها قطّ — ويُحتسب الحدّ
     * الأقصى بصفوف الربط لا بالنقرات، فيعود بعدد نقرات أقلّ بكثير
     * ممّا طُلب.
     *
     * القيد يربط لغة الصفّ بلغة البحث الذي أدّى إلى النقرة. والنقرة
     * بلا سجلّ بحث — نقرة مباشرة من صفحة تصفّح — تُقبل بأي لغة، لأنه
     * لا يوجد ما يُقيَّد به.
     *
     * ─── لماذا العنوان دون المتن ───────────────────────────────────
     *
     * المتن نثرٌ عام: كلماته مشتركة بين أغلب المستندات، فتتقارب ملفات
     * كل المستخدمين مهما تباعدت اهتماماتهم. وقد ظهر ذلك في القياس —
     * مستخدمان بذوقين متعاكسين حصلا على المضاعِف الأقصى نفسه في كل
     * استعلام لأن مفردات متن منتجاتهما تتقاطع.
     *
     * والعنوان أيضاً هو ما يُطابَق عليه في PersonalizationScorer،
     * فتوحيد طرفَي المقارنة شرط لعمل الإشارة أصلاً.
     *
     * @param  \Closure(Builder): mixed  $scope
     * @return string[]
     */
    private function clickedEntryTexts(
        \Closure $scope,
        int $projectId,
        int $days,
        int $limit
    ): array {
        $query = DB::table('user_click_logs as ucl')
            ->leftJoin('user_search_logs as usl', 'usl.id', '=', 'ucl.search_log_id')
            ->join('search_indices as si', 'si.entry_id', '=', 'ucl.entry_id')
            ->where('ucl.project_id', $projectId)
            ->where('ucl.clicked_at', '>=', now()->subDays($days))
            ->where(function ($group) {
                $group->whereNull('usl.language')
                    ->orWhereColumn('si.language', '=', 'usl.language');
            });

        $scope($query);

        /*
         | يُسحب فائض ثم تُوحَّد النقرات ويُقتطع.
         |
         | الحدّ في SQL يُطبَّق على صفوف الربط، وقد يقابل النقرةَ الواحدة
         | أكثر من صفّ حين تخلو النقرة من سجلّ بحث يقيّد لغتها.
         */
        $rows = $query
            ->orderByDesc('ucl.clicked_at')
            ->limit($limit * 3)
            ->select('ucl.id as click_id', 'si.title_fold')
            ->get();

        $texts = [];

        foreach ($rows as $row) {
            $text = trim((string) ($row->title_fold ?? ''));

            if ($text !== '' && ! isset($texts[$row->click_id])) {
                $texts[$row->click_id] = $text;
            }
        }

        return array_slice(array_values($texts), 0, $limit);
    }

    public function getRecentSearchTerms(
        int $projectId,
        int $userId,
        int $days = 30,
        int $limit = 10
    ): array {
        $rows = DB::table('user_search_logs')
            ->select('keyword', DB::raw('MAX(searched_at) as last_searched'))
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->where('searched_at', '>=', now()->subDays($days))
            ->whereNotNull('keyword')
            ->groupBy('keyword')
            ->orderByDesc('last_searched')
            ->limit($limit)
            ->get();

        $now = now();
        $terms = [];

        foreach ($rows as $row) {
            $lastSearched = Carbon::parse((string) $row->last_searched);

            /*
             | العمر بالقيمة المطلقة صراحةً.
             |
             | diffInDays في Carbon 3 موقَّعة، وسجلّ في الماضي يعطي
             | قيمة سالبة. abs() هنا تجعل العقد صريحاً: هذه الدالة
             | تعيد عمراً، والعمر لا يكون سالباً.
             */
            $ageDays = abs($lastSearched->diffInDays($now, false));

            foreach (Segmenter::tokenize(TextFolder::fold((string) $row->keyword)) as $token) {
                if (mb_strlen($token, 'UTF-8') < 2 || is_numeric($token)) {
                    continue;
                }

                // الورود الأحدث لمصطلح يغلب الأقدم.
                $terms[$token] = min($terms[$token] ?? PHP_FLOAT_MAX, (float) $ageDays);
            }
        }

        asort($terms);

        return array_map(
            static fn (string $term, float $age): array => ['term' => $term, 'age_days' => $age],
            array_keys($terms),
            array_values($terms)
        );
    }
}
