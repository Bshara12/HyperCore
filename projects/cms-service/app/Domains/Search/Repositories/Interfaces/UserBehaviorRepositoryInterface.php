<?php

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\LogClickDTO;
use App\Domains\Search\DTOs\LogSearchDTO;

interface UserBehaviorRepositoryInterface
{
    public function logSearch(LogSearchDTO $dto): int;  // يُرجع search_log_id

    public function logClick(LogClickDTO $dto): void;

    /**
     * @return array<int, int> [data_type_id => click_count]
     */
    public function getClickCountsByDataType(
        int $projectId,
        int $userId,
        int $days = 30
    ): array;

    /**
     * @return array<int, int>
     */
    public function getClickCountsByDataTypeForSession(
        int $projectId,
        string $sessionId,
        int $days = 30
    ): array;

    /**
     * جلب النص المفهرَس (title+content من search_indices) لأحدث $limit نقرة
     * لمستخدم مُسجَّل ضمن نافذة $days. يُستخدَم لبناء term affinity profile.
     *
     * @return string[]
     */
    public function getClickedEntryTexts(
        int $projectId,
        int $userId,
        int $days = 30,
        int $limit = 100
    ): array;

    /**
     * @return string[]
     */
    public function getClickedEntryTextsForSession(
        int $projectId,
        string $sessionId,
        int $days = 30,
        int $limit = 100
    ): array;
}