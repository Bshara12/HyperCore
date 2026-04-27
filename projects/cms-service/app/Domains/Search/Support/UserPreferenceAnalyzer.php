<?php

namespace App\Domains\Search\Support;

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class UserPreferenceAnalyzer
{
  /**
   * خريطة data_type_id → intent type
   * يجب تعديلها لتطابق IDs في مشروعك الفعلي
   * أو يمكن جلبها من data_types table ديناميكياً
   */
  private const DATA_TYPE_INTENT_MAP = [
    1 => 'product',   // products data type
    2 => 'article',   // articles data type
    3 => 'service',   // services data type
  ];

  private const CONFIDENCE_THRESHOLD = 0.35;
  private const CACHE_TTL_MINUTES    = 15;   // cache تفضيلات الـ user لـ 15 دقيقة
  private const ANALYSIS_DAYS        = 30;   // آخر 30 يوم فقط

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

    return Cache::remember(
      $cacheKey,
      now()->addMinutes(self::CACHE_TTL_MINUTES),
      function () use ($projectId, $userId) {
        $clickCounts = $this->repository->getClickCountsByDataType(
          $projectId,
          $userId,
          self::ANALYSIS_DAYS
        );

        return $this->buildPreference($clickCounts);
      }
    );
  }

  /**
   * تحليل تفضيلات guest عبر session
   */
  public function analyzeForSession(int $projectId, string $sessionId): UserPreferenceDTO
  {
    $cacheKey = "session_preference:{$projectId}:{$sessionId}";

    return Cache::remember(
      $cacheKey,
      now()->addMinutes(self::CACHE_TTL_MINUTES),
      function () use ($projectId, $sessionId) {
        $clickCounts = $this->repository->getClickCountsByDataTypeForSession(
          $projectId,
          $sessionId,
          self::ANALYSIS_DAYS
        );

        return $this->buildPreference($clickCounts);
      }
    );
  }

  /**
   * تحليل عام: user إذا وُجد، وإلا session
   */
  // public function analyze(
  //     int     $projectId,
  //     ?int    $userId,
  //     ?string $sessionId
  // ): UserPreferenceDTO {
  //     if ($userId !== null) {
  //         return $this->analyzeForUser($projectId, $userId);
  //     }

  //     if ($sessionId !== null) {
  //         return $this->analyzeForSession($projectId, $sessionId);
  //     }

  //     return UserPreferenceDTO::noHistory();
  // }
  public function analyze(
    int     $projectId,
    ?int    $userId,
    ?string $sessionId
  ): UserPreferenceDTO {

    // ─── DEBUG: تسجيل ما يصل فعلاً ──────────────────────────────
    \Illuminate\Support\Facades\Log::debug('UserPreferenceAnalyzer::analyze called', [
      'project_id' => $projectId,
      'user_id'    => $userId,
      'session_id' => $sessionId,
      'path'       => $userId !== null ? 'user' : ($sessionId !== null ? 'session' : 'no_history'),
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
   * تحويل click counts إلى UserPreferenceDTO
   *
   * مثال:
   *   clickCounts: [1 => 15, 2 => 3, 3 => 2]
   *   total:       20
   *   scores:      [product: 0.75, article: 0.15, service: 0.10]
   *   winner:      product (0.75 > threshold)
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

    // ─── تجميع الـ clicks حسب intent type ───────────────────────
    $intentScores = [
      'product' => 0,
      'article' => 0,
      'service' => 0,
    ];

    foreach ($clickCounts as $dataTypeId => $count) {
      $intent = self::DATA_TYPE_INTENT_MAP[$dataTypeId] ?? null;

      if ($intent !== null && isset($intentScores[$intent])) {
        $intentScores[$intent] += $count;
      }
    }

    // ─── تحويل إلى نسب ───────────────────────────────────────────
    $intentTotal = array_sum($intentScores);

    if ($intentTotal === 0) {
      return UserPreferenceDTO::noHistory();
    }

    $normalizedScores = [];
    foreach ($intentScores as $intent => $score) {
      $normalizedScores[$intent] = round($score / $intentTotal, 4);
    }

    // ─── الفائز ───────────────────────────────────────────────────
    arsort($normalizedScores);
    $winner     = array_key_first($normalizedScores);
    $confidence = $normalizedScores[$winner];

    if ($confidence < self::CONFIDENCE_THRESHOLD) {
      return new UserPreferenceDTO(
        preferredType: 'general',
        confidence: $confidence,
        typeScores: $normalizedScores,
        totalClicks: $totalClicks,
        hasHistory: true,
      );
    }

    return new UserPreferenceDTO(
      preferredType: $winner,
      confidence: $confidence,
      typeScores: $normalizedScores,
      totalClicks: $totalClicks,
      hasHistory: true,
    );
  }
}
