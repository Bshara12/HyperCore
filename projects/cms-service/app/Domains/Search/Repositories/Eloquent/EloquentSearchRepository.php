<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Support\ProcessedKeyword;
use Illuminate\Support\Facades\DB;

class EloquentSearchRepository implements SearchRepositoryInterface
{
  /**
   * خريطة ربط data_type slugs بالـ intents
   * يمكن تعديلها حسب slugs مشروعك الفعلية
   *
   * @var array<string, string[]>
   */
  private const INTENT_DATA_TYPE_MAP = [
    'product' => ['products', 'product', 'items', 'goods', 'منتجات'],
    'article' => ['articles', 'article', 'posts', 'blog', 'news', 'مقالات'],
    'service' => ['services', 'service', 'booking', 'appointments', 'خدمات'],
  ];

  // ─────────────────────────────────────────────────────────────────

  public function search(
    SearchQueryDTO    $dto,
    ProcessedKeyword  $processed,
    UserPreferenceDTO $preference
  ): array {
    foreach ($processed->relaxedQueries as $step => $query) {
      $result = $this->executeSearch($dto, $processed, $query, $preference);

      if ($result['total'] > 0) {
        $result['relaxation_step'] = $step;
        $result['query_used']      = $query;
        $result['intent']          = $processed->intent;
        return $result;
      }
    }

    return ['items' => [], 'total' => 0, 'relaxation_step' => -1, 'query_used' => null, 'intent' => $processed->intent];
  }

  // ─── في executeSearch أضف $preference parameter ──────────────────
  private function executeSearch(
    SearchQueryDTO    $dto,
    ProcessedKeyword  $processed,
    string            $booleanQuery,
    UserPreferenceDTO $preference     // ← إضافة
  ): array {
    $offset      = ($dto->page - 1) * $dto->perPage;
    $primaryWord = $processed->primaryWord;
    $intent      = $processed->intent['intent'];
    $confidence  = $processed->intent['confidence'];

    $rankingExpression  = $this->buildRankingExpression();
    $intentBoostExpr    = $this->buildIntentBoostExpression($intent, $confidence);

    // ─── Preference Boost (جديد) ──────────────────────────────────
    $prefBoostExpr      = $this->buildPreferenceBoostExpression($preference);

    $fullRanking = "({$rankingExpression}) + ({$intentBoostExpr}) + ({$prefBoostExpr})";

    $where      = 'si.project_id = ? AND si.language = ? AND si.status = ?';
    $whereBinds = [$dto->projectId, $dto->language, 'published'];

    $dataTypeJoin  = 'LEFT JOIN data_types dt ON dt.id = si.data_type_id';
    $dataTypeBinds = [];

    if ($dto->dataTypeSlug !== null) {
      $where        .= ' AND dt.slug = ?';
      $dataTypeBinds = [$dto->dataTypeSlug];
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM search_indices si
        {$dataTypeJoin}
        WHERE {$where}
          AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
    ";

    $countBinds = [...$whereBinds, ...$dataTypeBinds, $booleanQuery];
    $totalRow   = DB::selectOne($countSql, $countBinds);
    $total      = (int) ($totalRow->total ?? 0);

    if ($total === 0) return ['items' => [], 'total' => 0];

    $searchSql = "
        SELECT
            si.entry_id, si.data_type_id, si.project_id,
            si.language, si.title, si.content,
            si.status, si.published_at,
            ({$fullRanking}) AS weighted_score
        FROM search_indices si
        {$dataTypeJoin}
        WHERE {$where}
          AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
        ORDER BY weighted_score DESC
        LIMIT ? OFFSET ?
    ";

    $rankingBinds     = $this->buildRankingBinds($booleanQuery, $primaryWord);
    $intentBoostBinds = $this->buildIntentBoostBinds($intent, $confidence);
    $prefBoostBinds   = $this->buildPreferenceBoostBinds($preference);

    $searchBinds = [
      ...$rankingBinds,
      ...$intentBoostBinds,
      ...$prefBoostBinds,
      ...$whereBinds,
      ...$dataTypeBinds,
      $booleanQuery,
      $dto->perPage,
      $offset,
    ];

    $rows = DB::select($searchSql, $searchBinds);
    return ['items' => $rows, 'total' => $total];
  }

// ─────────────────────────────────────────────────────────────────
// Preference Boost Builders
// ─────────────────────────────────────────────────────────────────

  /**
   * Preference Boost أضعف من Intent Boost عمداً
   *
   * Intent Boost:      confidence × 2.5  (يعكس نية البحث الحالي)
   * Preference Boost:  confidence × 1.5  (يعكس تاريخ المستخدم)
   *
   * السبب: نية البحث الحالي أهم من التاريخ
   * لكن التاريخ يُكسر التعادل بين نتيجتين متساويتين
   */
  private function buildPreferenceBoostExpression(UserPreferenceDTO $preference): string
  {
    if (!$preference->hasHistory || $preference->preferredType === 'general') {
      return '0';
    }

    $slugs        = EloquentSearchRepository::INTENT_DATA_TYPE_MAP[$preference->preferredType] ?? [];
    if (empty($slugs)) return '0';

    $placeholders = implode(', ', array_fill(0, count($slugs), '?'));

    return "
        CASE
            WHEN dt.slug IN ({$placeholders})
            THEN ? * 1.5
            ELSE 0
        END
    ";
  }

  private function buildPreferenceBoostBinds(UserPreferenceDTO $preference): array
  {
    if (!$preference->hasHistory || $preference->preferredType === 'general') {
      return [];
    }

    $slugs = EloquentSearchRepository::INTENT_DATA_TYPE_MAP[$preference->preferredType] ?? [];
    if (empty($slugs)) return [];

    return [...$slugs, $preference->confidence];
  }

  // ─────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────
    // Intent Boost Expression
    // ─────────────────────────────────────────────────────────────────

  /**
   * بناء SQL expression لـ boost النتائج حسب النية
   *
   * المنطق:
   *   إذا كانت النية "product" والـ data_type slug يدل على products
   *   → أضف boost مضروباً في الـ confidence
   *
   *   هذا يعني:
   *   - confidence عالي (0.9) → boost كبير
   *   - confidence منخفض (0.3) → boost صغير
   *   - general intent → لا boost
   *
   * ملاحظة: إذا كانت النية general → نُرجع 0 دائماً
   */
  private function buildIntentBoostExpression(
    string $intent,
    float  $confidence
  ): string {
    // general = لا boost على الإطلاق
    if ($intent === 'general' || $confidence < 0.3) {
      return '0';
    }

    $slugs = self::INTENT_DATA_TYPE_MAP[$intent] ?? [];

    if (empty($slugs)) {
      return '0';
    }

    /*
         * بناء قائمة الـ slugs كـ placeholders
         * مثال: 3 slugs → "?, ?, ?"
         */
    $placeholders = implode(', ', array_fill(0, count($slugs), '?'));

    /*
         * الصيغة:
         *   CASE
         *     WHEN dt.slug IN (?, ?, ?) THEN ? * 2.5
         *     ELSE 0
         *   END
         *
         * المضاعف 2.5 = قيمة الـ base boost
         * × confidence = يُخفف الـ boost إذا كنا غير متأكدين
         */
    return "
            CASE
                WHEN dt.slug IN ({$placeholders})
                THEN ? * 2.5
                ELSE 0
            END
        ";
  }

  /**
   * Binds لـ Intent Boost Expression
   *
   * @return array
   */
  private function buildIntentBoostBinds(string $intent, float $confidence): array
  {
    if ($intent === 'general' || $confidence < 0.3) {
      return [];
    }

    $slugs = self::INTENT_DATA_TYPE_MAP[$intent] ?? [];

    if (empty($slugs)) {
      return [];
    }

    // slugs أولاً (للـ IN)، ثم confidence (للضرب)
    return [...$slugs, $confidence];
  }

  // ─────────────────────────────────────────────────────────────────
  // Ranking (لم يتغير)
  // ─────────────────────────────────────────────────────────────────

  private function buildRankingExpression(): string
  {
    $scoreA = "MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) * 3";
    $scoreB = "CASE WHEN LOCATE(?, title) > 0 THEN 2.0 ELSE 0 END";
    $scoreC = "CASE WHEN title LIKE ? THEN 1.5 ELSE 0 END";
    $scoreD_title   = "COALESCE(1.0 / (NULLIF(LOCATE(?, title), 0) + 1), 0)";
    $scoreD_content = "COALESCE(1.0 / (NULLIF(LOCATE(?, content), 0) + 1), 0) * 0.5";
    $scoreE = "
            (
                (
                    CHAR_LENGTH(CONCAT(title, ' ', content))
                    - CHAR_LENGTH(
                        REPLACE(
                            LOWER(CONCAT(title, ' ', content)),
                            LOWER(?),
                            ''
                        )
                    )
                ) / NULLIF(CHAR_LENGTH(?), 0)
            ) * 0.1
        ";

    return "
            ({$scoreA}) + ({$scoreB}) + ({$scoreC})
            + ({$scoreD_title}) + ({$scoreD_content}) + ({$scoreE})
        ";
  }

  private function buildRankingBinds(string $naturalQuery, string $primaryWord): array
  {
    return [
      $naturalQuery,
      $primaryWord,
      '%' . $primaryWord . '%',
      $primaryWord,
      $primaryWord,
      $primaryWord,
      $primaryWord,
    ];
  }
}
