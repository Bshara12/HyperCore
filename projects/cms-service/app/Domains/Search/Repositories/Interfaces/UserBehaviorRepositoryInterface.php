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

    /**
     * مصطلحات بحث المستخدم الأخيرة، بأعمارها بالأيام.
     *
     * العمر يُعاد كعدد موجب صريح لا كفارق تواريخ يحسبه المستهلك.
     *
     * السبب أن الإصدار السابق كان يستدعي now()->diffInDays($past) في
     * طبقة الترتيب ويفترض قيمة موجبة، بينما تعيدها Carbon 3 موقَّعةً —
     * فكانت سالبة، فينقلب الاضمحلال الأسّي نموّاً ويصير أقدمُ اهتمامات
     * المستخدم أثقلَها وزناً. إعادة العمر جاهزاً من هنا تمنع تكرار
     * الافتراض نفسه في كل موضع يستهلك التاريخ.
     *
     * @return array<int, array{term: string, age_days: float}>
     */
    public function getRecentSearchTerms(
        int $projectId,
        int $userId,
        int $days = 30,
        int $limit = 10
    ): array;
}
