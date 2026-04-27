<?php

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\LogClickDTO;
use App\Domains\Search\DTOs\LogSearchDTO;

interface UserBehaviorRepositoryInterface
{
    public function logSearch(LogSearchDTO $dto): int;  // يُرجع search_log_id

    public function logClick(LogClickDTO $dto): void;

    /**
     * جلب إحصائيات نقرات الـ user مجمّعة حسب data_type
     * في آخر N يوم
     *
     * @return array<int, int>  [data_type_id => click_count]
     */
    public function getClickCountsByDataType(
        int $projectId,
        int $userId,
        int $days = 30
    ): array;

    /**
     * نفس الشيء للـ guest عبر session
     *
     * @return array<int, int>
     */
    public function getClickCountsByDataTypeForSession(
        int    $projectId,
        string $sessionId,
        int    $days = 30
    ): array;
}