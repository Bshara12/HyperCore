<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\LogClickDTO;
use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\Models\UserClickLog;
use App\Domains\Search\Models\UserSearchLog;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
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
     * نصوص المدخلات التي نقر عليها المستخدم — أساس الـ termAffinities.
     *
     * تفصيلان مهمّان:
     *
     * 1. **صف واحد لكل نقرة**: الـ JOIN على entry_id وحده يُرجع صفاً لكل
     *    لغة مفهرسة. في مشروع ثنائي اللغة كانت النقرة الواحدة تُنتج نصّين،
     *    فيُحتسب المصطلح مرتين ويتجاوز MIN_TERM_SIGNAL (= نقرتان) من
     *    نقرة واحدة، وتُملأ حصة VOCAB_CAP بكلمات اللغة الأخرى.
     *
     * 2. **لغة النقرة**: تُستنتج من سجل البحث المرتبط (search_log_id).
     *    من بحث بالعربية لا يجوز أن نتعلّم مفردات إنجليزية.
     *    النقرات بلا سجل بحث (نقر مباشر) تُقبل بأي لغة ثم تُلتقط
     *    صفاً واحداً فقط.
     *
     * @param  \Closure(\Illuminate\Database\Query\Builder): mixed  $scope
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
            ->where(function ($q) {
                $q->whereNull('usl.language')
                    ->orWhereColumn('si.language', '=', 'usl.language');
            });

        $scope($query);

        // الـ limit يُطبَّق على صفوف الـ JOIN، فنجلب فائضاً ثم نُوحّد
        // النقرات ونقتطع — وإلا رجعنا بعدد نقرات أقل من المطلوب.
        $rows = $query
            ->orderByDesc('ucl.clicked_at')
            ->limit($limit * 3)
            ->select('ucl.id as click_id', 'si.title', 'si.content')
            ->get();

        $texts = [];

        foreach ($rows as $row) {
            if (isset($texts[$row->click_id])) {
                continue;
            }

            $text = trim(implode(' ', array_filter([
                (string) ($row->title ?? ''),
                (string) ($row->content ?? ''),
            ], fn ($part) => trim($part) !== '')));

            if ($text !== '') {
                $texts[$row->click_id] = $text;
            }
        }

        return array_slice(array_values($texts), 0, $limit);
    }
}