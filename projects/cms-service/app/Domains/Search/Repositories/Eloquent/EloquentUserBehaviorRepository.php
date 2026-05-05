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
}
