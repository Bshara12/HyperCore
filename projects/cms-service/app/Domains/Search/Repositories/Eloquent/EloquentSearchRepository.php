<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Support\ProcessedKeyword;
use App\Domains\Search\Support\SearchResultRanker;
use Illuminate\Support\Facades\DB;

class EloquentSearchRepository implements SearchRepositoryInterface
{
  /*
     * جلب top 100 من DB ثم re-rank في PHP
     * هذا أسرع من حساب complex scoring على ملايين الصفوف في SQL
     */
  private const DB_FETCH_LIMIT = 100;

  private const INTENT_DATA_TYPE_MAP = [
    'product' => ['products', 'product', 'items', 'goods', 'منتجات'],
    'article' => ['articles', 'article', 'posts', 'blog', 'news', 'مقالات'],
    'service' => ['services', 'service', 'booking', 'appointments', 'خدمات'],
  ];

  public function __construct(
    private SearchResultRanker $ranker,
  ) {}

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

    return [
      'items'           => [],
      'total'           => 0,
      'relaxation_step' => -1,
      'query_used'      => null,
      'intent'          => $processed->intent,
    ];
  }

  // ─────────────────────────────────────────────────────────────────

  private function executeSearch(
    SearchQueryDTO    $dto,
    ProcessedKeyword  $processed,
    string            $booleanQuery,
    UserPreferenceDTO $preference
  ): array {

    $intent     = $processed->intent['intent'];
    $confidence = $processed->intent['confidence'];
    $cleanWords = $processed->cleanWords;
    $phraseQuery = implode(' ', $cleanWords);

    // ─── 1. بناء WHERE conditions ─────────────────────────────────
    $where      = 'si.project_id = ? AND si.language = ? AND si.status = ?';
    $binds      = [$dto->projectId, $dto->language, 'published'];
    // $binds = [1, 'en', 'published'];
    $excludeTerms = [];

    preg_match_all('/-([^\s]+)/', $booleanQuery, $matches);

    $excludeTerms = $matches[1] ?? [];

    $excludeLikeConditions = [];
    $excludeLikeBinds      = [];

    foreach ($excludeTerms as $term) {

      $cleanTerm = trim($term);

      if ($cleanTerm === '') {
        continue;
      }

      $excludeLikeConditions[] =
        "CONCAT_WS(' ', si.title, si.content) NOT LIKE ?";

      $excludeLikeBinds[] = '%' . $cleanTerm . '%';
    }


    if (!empty($excludeLikeConditions)) {
      $where .= ' AND ' . implode(' AND ', $excludeLikeConditions);
    }
    /*
         * Intent Filtering بدون JOIN:
         * نستخدم data_type_slug المخزون في search_indices مباشرة
         * بدل LEFT JOIN data_types
         * هذا يوفر الـ JOIN cost على ملايين الصفوف
         */
    if ($dto->dataTypeSlug !== null) {
      $where .= ' AND si.data_type_slug = ?';
      $binds[] = $dto->dataTypeSlug;
    } elseif ($intent !== 'general' && $confidence >= 0.3) {
      $intentSlugs = self::INTENT_DATA_TYPE_MAP[$intent] ?? [];
      if (!empty($intentSlugs)) {
        $placeholders = implode(', ', array_fill(0, count($intentSlugs), '?'));
        $where .= " AND si.data_type_slug IN ({$placeholders})";
        $binds  = array_merge($binds, $intentSlugs);
      }
    }

    // ─── 2. COUNT query مُبسَّط ───────────────────────────────────
    /*
         * بدل COUNT(*) الثقيل، نستخدم SQL_CALC_FOUND_ROWS
         * أو نقبل بـ estimated count من information_schema
         *
         * لكن الأبسط والأكثر موثوقية: count مع LIMIT على الـ FULLTEXT
         */
    $countSql = "
    SELECT COUNT(*) AS total
    FROM search_indices si
    WHERE {$where}
      AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
    LIMIT 10000
";
    // dd("countSql", $countSql);
    // $binds = [1, 'en', 'published'];
    $countBinds = [
      ...$binds,
      ...$excludeLikeBinds,
      $booleanQuery,
    ];
    $countRow = DB::selectOne($countSql, $countBinds);
    $total    = (int) ($countRow->total ?? 0);

    if ($total === 0) {
      return ['items' => [], 'total' => 0];
    }

    // ─── 3. Search Query مُبسَّط - فقط FULLTEXT + precomputed ─────
    /*
         * الـ SQL الجديد بسيط جداً:
         *   - FULLTEXT فقط للـ filtering والـ base score
         *   - لا LIKE، لا LOCATE، لا REPLACE، لا CHAR_LENGTH
         *   - نجلب ctr_score و freshness_score من الأعمدة المُحسوبة
         *   - الـ re-ranking المعقد يحدث في PHP
         *
         * هذا يُقلل query complexity من O(n × ops) إلى O(n × 1)
         */
    // $searchSql = "
    //     SELECT
    //         si.entry_id,
    //         si.data_type_id,
    //         si.data_type_slug,
    //         si.project_id,
    //         si.language,
    //         si.title,
    //         si.content,
    //         si.status,
    //         si.published_at,
    //         si.ctr_score,
    //         si.freshness_score,
    //         si.title_has_numbers,
    //         si.title_word_count,
    //         MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) AS fulltext_score
    //     FROM search_indices si
    //     WHERE {$where}
    //       AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
    //     ORDER BY fulltext_score DESC
    //     LIMIT ?
    //     OFFSET ?
    // ";
    $searchSql = "
    SELECT
        si.entry_id,
        si.data_type_id,
        si.data_type_slug,
        si.project_id,
        si.language,
        si.title,
        si.content,
        si.status,
        si.published_at,
        si.ctr_score,
        si.freshness_score,
        si.title_has_numbers,
        si.title_word_count,
        si.click_count,
        si.view_count,
        si.popularity_score,
        MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) AS fulltext_score
    FROM search_indices si
    WHERE {$where}
      AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
    ORDER BY fulltext_score DESC
    LIMIT ?
    OFFSET ?
";


    /*
         * Offset Strategy:
         * للصفحة 1: جلب top 100، re-rank، أرجع perPage
         * للصفحات الأخرى: offset عادي (acceptable للصفحات الأولى فقط)
         *
         * للـ scale الحقيقي: cursor-based pagination
         */
    $fetchLimit = max(self::DB_FETCH_LIMIT, $dto->perPage * 3);
    $offset     = max(0, ($dto->page - 1) * $dto->perPage - $fetchLimit + $dto->perPage);

    // $searchBinds = [
    //   $booleanQuery,  // NATURAL LANGUAGE للـ scoring
    //   ...$binds,      // WHERE conditions
    //   $booleanQuery,  // BOOLEAN MODE للـ filtering
    //   $fetchLimit,    // نجلب أكثر من المطلوب للـ re-ranking
    //   0,              // offset = 0 للـ page 1 (re-ranking يتولى الـ pagination)
    // ];


    $searchBinds = [
      $booleanQuery,       // أول AGAINST
      ...$binds,           // where الأساسي
      ...$excludeLikeBinds, // NOT LIKE
      $booleanQuery,       // ثاني AGAINST
      $fetchLimit,
      0,
    ];

    $rows = DB::select($searchSql, $searchBinds);
    if (empty($rows)) {
      return ['items' => [], 'total' => $total];
    }

    // ─── 4. PHP Re-ranking ────────────────────────────────────────
    $userKeywords = $this->getUserKeywords($dto->userId, $dto->projectId);

    $reranked = $this->ranker->rerank(
      rows: $rows,
      cleanWords: $cleanWords,
      phraseQuery: $phraseQuery,
      intent: $intent,
      intentConf: $confidence,
      preference: $preference,
      userKeywords: $userKeywords,
    );

    // ─── 5. Pagination بعد الـ Re-ranking ────────────────────────
    $pageStart   = ($dto->page - 1) * $dto->perPage;
    $pagedItems  = array_slice($reranked, $pageStart, $dto->perPage);


    return [
      'items' => $pagedItems,
      'total' => $total,
    ];
  }

  // ─────────────────────────────────────────────────────────────────
  // User Keywords (Time-Decayed)
  // ─────────────────────────────────────────────────────────────────

  private function getUserKeywords(?int $userId, int $projectId): array
  {
    if ($userId === null) {
      return [];
    }

    static $cache = []; // request-level cache

    $cacheKey = "{$userId}:{$projectId}";
    if (isset($cache[$cacheKey])) {
      return $cache[$cacheKey];
    }

    $rows = DB::table('user_search_logs')
      ->select('keyword', DB::raw('MAX(searched_at) as last_searched'))
      // ->select('keyword', DB::raw('COUNT(*) as search_count,(searched_at) as last_searched'))
      ->where('user_id', $userId)
      ->where('project_id', $projectId)
      ->where('searched_at', '>=', now()->subDays(14))
      ->whereNotNull('keyword')
      ->whereRaw('CHAR_LENGTH(TRIM(keyword)) >= 2')
      ->groupBy('keyword')
      ->orderByDesc('last_searched')
      ->limit(8)
      ->get();

    $result = [];

    foreach ($rows as $row) {
      $daysAgo = now()->diffInDays($row->last_searched);
      $weight  = exp(-$daysAgo / 7.0);

      $words = preg_split('/\s+/', mb_strtolower(trim($row->keyword)));

      foreach ($words as $word) {
        if (mb_strlen($word) >= 3 && !is_numeric($word)) {
          $result[$word] = max($result[$word] ?? 0, round($weight, 4));
        }
      }
    }

    arsort($result);
    $result = array_slice(
      array_map(
        fn($w, $weight) => ['word' => $w, 'weight' => $weight],
        array_keys($result),
        array_values($result)
      ),
      0,
      10
    );

    return $cache[$cacheKey] = $result;
  }

  // ─────────────────────────────────────────────────────────────────
  // Click Tracking
  // ─────────────────────────────────────────────────────────────────

  public function incrementClickCount(int $entryId, string $language): void
  {
    DB::table('search_indices')
      ->where('entry_id', $entryId)
      ->where('language', $language)
      ->increment('click_count');
  }











  /**
   * بحث مع دعم كلمات الاستبعاد (BOOLEAN MODE minus)
   *
   * @param string[] $excludeTerms  كلمات يجب ألا تظهر في النتائج
   */
  public function searchWithExclusions(
    SearchQueryDTO    $dto,
    ProcessedKeyword  $processed,
    UserPreferenceDTO $preference,
    array             $excludeTerms = []
  ): array {
    /*
     * إذا لا يوجد استبعاد → استخدم الـ search العادي
     */
    if (empty($excludeTerms)) {
      return $this->search($dto, $processed, $preference);
    }

    /*
     * بناء relaxed queries مع إضافة الاستبعاد لكل منها
     *
     * مثال:
     *   original relaxed: ["+iphone*", "iphone*"]
     *   مع exclude "15":  ["+iphone* -15", "iphone* -15"]
     */



    $excludeSuffix = ' ' . implode(
      ' ',
      array_map(
        fn($t) => '-' . preg_replace('/[+\-><\(\)~*"@]+/', '', $t),
        $excludeTerms
      )
    );
    $modifiedProcessed = $this->injectExclusionsIntoProcessed(
      $processed,
      $excludeSuffix
    );

    return $this->search($dto, $modifiedProcessed, $preference);
  }

  /**
   * حقن الـ exclude suffix في كل relaxed queries
   */
  private function injectExclusionsIntoProcessed(
    ProcessedKeyword $processed,
    string           $excludeSuffix
  ): ProcessedKeyword {

    $modifiedQueries = array_map(
      fn($q) => $q . $excludeSuffix,
      $processed->relaxedQueries
    );

    return new ProcessedKeyword(
      original: $processed->original,
      booleanQuery: $processed->booleanQuery . $excludeSuffix,
      cleanWords: $processed->cleanWords,
      primaryWord: $processed->primaryWord,
      relaxedQueries: $modifiedQueries,
      expandedGroups: $processed->expandedGroups,
      intent: $processed->intent,
      dbExpandedGroups: $processed->dbExpandedGroups,
      hadDbExpansion: $processed->hadDbExpansion,
    );
  }
}
