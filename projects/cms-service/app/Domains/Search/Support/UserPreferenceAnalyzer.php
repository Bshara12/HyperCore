<?php

namespace App\Domains\Search\Support;

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * UserPreferenceAnalyzer — Data-Type-Level Affinity Model
 *
 * الإصدار السابق كان يُجمّع كل data_type_id ضمن 3 فئات عريضة ثابتة
 * (product/article/service) عبر DATA_TYPE_INTENT_MAP، مما كان يُفقد
 * التمييز بين phones/laptops/tablets مثلاً (كلها كانت تُختزل إلى "product").
 *
 * هذا الإصدار يحسب affinity مستقلة لكل data_type_id فعلي، مبنية على
 * click count خام من الـ Repository ضمن نافذة تحليل ثابتة (ANALYSIS_DAYS).
 */
class UserPreferenceAnalyzer
{
    /**
     * الحد الأدنى لعدد النقرات حتى يُعتبر data_type إشارة ذات معنى.
     * أقل من هذا يُعتبر ضجيجاً (نقرة عرضية) ويُتجاهل تماماً.
     */
    private const MIN_CLICKS_FOR_SIGNAL = 2;

    /**
     * ثابت التشبّع K في صيغة: affinity = count / (count + K)
     */
    private const SATURATION_K = 3.0;

    private const CACHE_TTL_MINUTES = 15;   // cache تفضيلات الـ user لـ 15 دقيقة

    private const ANALYSIS_DAYS = 30;   // آخر 30 يوم فقط

    public function __construct(
        private UserBehaviorRepositoryInterface $repository,
    ) {}

    // ─────────────────────────────────────────────────────────────────

    /**
     * تحليل تفضيلات user مُسجَّل
     */
    public function analyzeForUser(int $projectId, int $userId): UserPreferenceDTO
    {
        $cacheKey = "user_preference:{$projectId}:{$userId}";

        return $this->resolveFromCache(
            $cacheKey,
            fn () => $this->buildPreference(
                $this->repository->getClickCountsByDataType($projectId, $userId, self::ANALYSIS_DAYS)
            )
        );
    }

    /**
     * تحليل تفضيلات guest عبر session
     */
    public function analyzeForSession(int $projectId, string $sessionId): UserPreferenceDTO
    {
        $cacheKey = "session_preference:{$projectId}:{$sessionId}";

        return $this->resolveFromCache(
            $cacheKey,
            fn () => $this->buildPreference(
                $this->repository->getClickCountsByDataTypeForSession($projectId, $sessionId, self::ANALYSIS_DAYS)
            )
        );
    }

    /**
     * تحليل عام: user إذا وُجد، وإلا session
     */
    public function analyze(
        int $projectId,
        ?int $userId,
        ?string $sessionId
    ): UserPreferenceDTO {

        // ─── DEBUG: تسجيل ما يصل فعلاً ──────────────────────────────
        Log::debug('UserPreferenceAnalyzer::analyze called', [
            'project_id' => $projectId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'path' => $userId !== null ? 'user' : ($sessionId !== null ? 'session' : 'no_history'),
        ]);

        if ($userId !== null) {
            return $this->analyzeForUser($projectId, $userId);
        }

        if ($sessionId !== null) {
            return $this->analyzeForSession($projectId, $sessionId);
        }

        return UserPreferenceDTO::noHistory();
    }

    /**
     * مسح الـ cache عند تسجيل نقرة جديدة
     * (حتى يُعاد حساب التفضيلات)
     */
    public function invalidateCache(int $projectId, int $userId): void
    {
        Cache::forget("user_preference:{$projectId}:{$userId}");
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * قراءة آمنة من الكاش مع rebuild تلقائي عند أي corruption.
     *
     * السبب: Cache::remember() يُرجع القيمة المخزَّنة "كما هي" بلا فحص نوع،
     * فإذا فشل unserialize() داخلياً (مثلاً بعد تغيير بنية UserPreferenceDTO
     * في نشر سابق) قد يُرجع false أو قيمة غريبة أخرى، وهي قيمة "غير null"
     * فيعتبرها remember() صالحة ويُرجعها مباشرة → استدعاء affinityFor() على
     * قيمة ليست UserPreferenceDTO يُسبب Fatal Error في SearchResultRanker.
     *
     * الحل: قراءة صريحة عبر Cache::get()، فحص instanceof، وإعادة البناء
     * من الـ Repository فوراً عند أي عدم تطابق (بدل الوثوق بالقيمة عمياء).
     */
    private function resolveFromCache(string $cacheKey, \Closure $builder): UserPreferenceDTO
    {
        $cached = Cache::get($cacheKey);

        if ($cached instanceof UserPreferenceDTO) {
            return $cached;
        }

        if ($cached !== null) {
            Log::warning('UserPreferenceAnalyzer: unexpected cache value type, rebuilding from source', [
                'cache_key' => $cacheKey,
                'actual_type' => get_debug_type($cached),
            ]);
        }

        $fresh = $builder();

        Cache::put($cacheKey, $fresh, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $fresh;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تحويل click counts إلى UserPreferenceDTO — Data-Type-Level Affinity
     *
     * مثال:
     *   clickCounts: [101 => 12, 205 => 8, 310 => 1]   (101=phones, 205=laptops, 310=tablets)
     *   totalClicks: 21
     *   310 مستبعد لأن count=1 < MIN_CLICKS_FOR_SIGNAL
     *   affinities:  [101 => 0.80, 205 => 0.73]         (كل data_type مستقل، بلا تطبيع لمجموع=1)
     */
    private function buildPreference(array $clickCounts): UserPreferenceDTO
    {
        if (empty($clickCounts)) {
            return UserPreferenceDTO::noHistory();
        }

        $totalClicks = array_sum($clickCounts);

        if ($totalClicks === 0) {
            return UserPreferenceDTO::noHistory();
        }

        // ─── affinity مستقلة لكل data_type_id (بلا تجميع في فئات) ─────
        $affinities = [];

        foreach ($clickCounts as $dataTypeId => $count) {
            $count = (int) $count;

            if ($count < self::MIN_CLICKS_FOR_SIGNAL) {
                continue;
            }

            $affinities[(int) $dataTypeId] = round(
                $count / ($count + self::SATURATION_K),
                4
            );
        }

        if (empty($affinities)) {
            return UserPreferenceDTO::noHistory();
        }

        return new UserPreferenceDTO(
            affinities: $affinities,
            totalClicks: $totalClicks,
            hasHistory: true,
        );
    }
}