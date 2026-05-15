<?php

declare(strict_types=1);

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Support\ProcessedKeyword;
use App\Domains\Search\Support\SearchResultRanker;
use App\Domains\Search\Support\SqlFragment;
use Illuminate\Support\Facades\DB;

final class EloquentSearchRepository implements SearchRepositoryInterface
{
  private const DB_FETCH_LIMIT = 100;

  private const INTENT_DATA_TYPE_MAP = [
    'product' => ['products', 'product', 'items', 'goods'],
    'article' => ['articles', 'article', 'posts', 'blog', 'news'],
    'service' => ['services', 'service', 'booking', 'appointments'],
  ];

  public function __construct(
    private readonly SearchResultRanker $ranker,
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
        return array_merge($result, [
          'relaxation_step' => $step,
          'query_used'      => $query,
          'intent'          => $processed->intent,
        ]);
      }
    }

    return ['items' => [], 'total' => 0, 'relaxation_step' => -1, 'query_used' => null, 'intent' => $processed->intent];
  }

  // ─────────────────────────────────────────────────────────────────

  private function executeSearch(
    SearchQueryDTO    $dto,
    ProcessedKeyword  $processed,
    string            $booleanQuery,
    UserPreferenceDTO $preference
  ): array {
    $intent      = $processed->intent['intent'];
    $confidence  = $processed->intent['confidence'];
    $cleanWords  = $processed->cleanWords;
    $phraseQuery = implode(' ', $cleanWords);

    // ── WHERE Fragment (SQL + bindings مُدمجان) ──────────────────
    $where = $this->buildWhereFragment($dto, $booleanQuery, $intent, $confidence);

    // ── COUNT ─────────────────────────────────────────────────────
    $total = $this->fetchCount($where, $booleanQuery);
    if ($total === 0) {
      return ['items' => [], 'total' => 0];
    }

    // ── SEARCH ───────────────────────────────────────────────────
    $rows = $this->fetchRows($where, $booleanQuery, $dto);
    if (empty($rows)) {
      return ['items' => [], 'total' => $total];
    }

    // ── Re-ranking ────────────────────────────────────────────────
    $userKeywords = $this->getUserKeywords($dto->userId, $dto->projectId);
    $reranked     = $this->ranker->rerank(
      rows: $rows,
      cleanWords: $cleanWords,
      phraseQuery: $phraseQuery,
      intent: $intent,
      intentConf: $confidence,
      preference: $preference,
      userKeywords: $userKeywords,
    );

    $pageStart  = ($dto->page - 1) * $dto->perPage;
    $pagedItems = array_slice($reranked, $pageStart, $dto->perPage);

    return ['items' => $pagedItems, 'total' => $total];
  }

    // ─────────────────────────────────────────────────────────────────
    // WHERE Fragment Builder — الإصلاح الجوهري
    // ─────────────────────────────────────────────────────────────────

  /**
   * يُبني WHERE fragment حيث كل condition يحمل bindings معه.
   * مستحيل نسيان binding أو اختلاف ترتيبه.
   *
   * @param string $booleanQuery  لاستخراج exclude terms منه (prefix -)
   */
  private function buildWhereFragment(
    SearchQueryDTO $dto,
    string         $booleanQuery,
    string         $intent,
    float          $confidence
  ): SqlFragment {
    // Base conditions
    $fragment = SqlFragment::create()
      ->and('si.project_id = ?', [$dto->projectId])
      ->and('si.language = ?',   [$dto->language])
      ->and('si.status = ?',     ['published']);

    // Exclude terms من BOOLEAN MODE (كلمات تبدأ بـ -)
    $excludeTerms = $this->extractExcludeTerms($booleanQuery);
    if (! empty($excludeTerms)) {
      $fragment = $fragment->andNotLikeAll(
        "CONCAT_WS(' ', si.title, si.content)",
        $excludeTerms
      );
    }

    // DataType filter
    if ($dto->dataTypeSlug !== null) {
      $fragment = $fragment->and('si.data_type_slug = ?', [$dto->dataTypeSlug]);
    } elseif ($intent !== 'general' && $confidence >= 0.3) {
      $intentSlugs = self::INTENT_DATA_TYPE_MAP[$intent] ?? [];
      if (! empty($intentSlugs)) {
        $fragment = $fragment->andIn('si.data_type_slug', $intentSlugs);
      }
    }

    return $fragment;
  }

  /**
   * استخراج exclude terms من BOOLEAN MODE query
   * "+iphone* -14 -case" → ["14", "case"]
   */
  private function extractExcludeTerms(string $booleanQuery): array
  {
    preg_match_all('/-([^\s+\-><\(\)~*"@]+)/', $booleanQuery, $matches);
    return array_values(array_filter(
      array_map('trim', $matches[1] ?? []),
      fn($t) => $t !== ''
    ));
  }

  // ─────────────────────────────────────────────────────────────────
  // SQL Execution — bindings تأتي من Fragment مباشرة
  // ─────────────────────────────────────────────────────────────────

  private function fetchCount(SqlFragment $where, string $booleanQuery): int
  {
    $sql = "
            SELECT COUNT(*) AS total
            FROM search_indices si
            WHERE {$where->sql}
              AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
            LIMIT 10000
        ";

    // ترتيب bindings واضح ومحدود: WHERE bindings + AGAINST binding
    $bindings = [...$where->bindings, $booleanQuery];

    $row = DB::selectOne($sql, $bindings);

    return (int) ($row->total ?? 0);
  }

  private function fetchRows(SqlFragment $where, string $booleanQuery, SearchQueryDTO $dto): array
  {
    $sql = "
            SELECT
                si.entry_id, si.data_type_id, si.data_type_slug,
                si.project_id, si.language, si.title, si.content,
                si.status, si.published_at, si.ctr_score, si.freshness_score,
                si.title_has_numbers, si.title_word_count,
                si.click_count, si.view_count, si.popularity_score,
                MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) AS fulltext_score
            FROM search_indices si
            WHERE {$where->sql}
              AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
            ORDER BY fulltext_score DESC
            LIMIT ? OFFSET ?
        ";

    $fetchLimit = max(self::DB_FETCH_LIMIT, $dto->perPage * 3);

    // ترتيب bindings:
    // 1. NATURAL LANGUAGE AGAINST (للـ scoring)
    // 2. WHERE bindings
    // 3. BOOLEAN AGAINST (للـ filtering)
    // 4. LIMIT, OFFSET
    $bindings = [
      $booleanQuery,      // NATURAL LANGUAGE score
      ...$where->bindings, // WHERE
      $booleanQuery,      // BOOLEAN filter
      $fetchLimit,
      0,
    ];

    return DB::select($sql, $bindings);
  }

  // ─────────────────────────────────────────────────────────────────
  // searchWithExclusions
  // ─────────────────────────────────────────────────────────────────

  public function searchWithExclusions(
    SearchQueryDTO    $dto,
    ProcessedKeyword  $processed,
    UserPreferenceDTO $preference,
    array             $excludeTerms = []
  ): array {
    // Exclude-only: لا FULLTEXT query
    if (empty($processed->cleanWords) && ! empty($excludeTerms)) {
      return $this->searchExcludeOnly($dto, $excludeTerms, $preference);
    }

    if (empty($excludeTerms)) {
      return $this->search($dto, $processed, $preference);
    }

    $excludeSuffix = ' ' . implode(
      ' ',
      array_map(
        fn($t) => '-' . preg_replace('/[+\-><\(\)~*"@]+/', '', $t),
        $excludeTerms
      )
    );

    return $this->search($dto, $this->injectExclusions($processed, $excludeSuffix), $preference);
  }

  /**
   * Exclude-only search — يستخدم NOT LIKE بدل FULLTEXT
   * لأن MySQL FULLTEXT لا يعمل مع empty query
   */
  private function searchExcludeOnly(
    SearchQueryDTO    $dto,
    array             $excludeTerms,
    UserPreferenceDTO $preference
  ): array {
    $where = SqlFragment::create()
      ->and('si.project_id = ?', [$dto->projectId])
      ->and('si.language = ?',   [$dto->language])
      ->and('si.status = ?',     ['published'])
      ->andIf($dto->dataTypeSlug !== null, 'si.data_type_slug = ?', [$dto->dataTypeSlug ?? ''])
      ->andNotLikeAll("CONCAT_WS(' ', si.title, si.content)", $excludeTerms);

    $countSql = "SELECT COUNT(*) AS total FROM search_indices si WHERE {$where->sql} LIMIT 10000";
    $total    = (int) (DB::selectOne($countSql, $where->bindings)->total ?? 0);

    if ($total === 0) return ['items' => [], 'total' => 0];

    $fetchLimit = max(self::DB_FETCH_LIMIT, $dto->perPage * 3);
    $offset     = max(0, ($dto->page - 1) * $dto->perPage);

    $searchSql = "
            SELECT
                si.entry_id, si.data_type_id, si.data_type_slug,
                si.project_id, si.language, si.title, si.content,
                si.status, si.published_at, si.ctr_score, si.freshness_score,
                si.title_has_numbers, si.title_word_count,
                si.click_count, si.view_count, si.popularity_score,
                si.popularity_score AS fulltext_score
            FROM search_indices si
            WHERE {$where->sql}
            ORDER BY si.popularity_score DESC, si.published_at DESC
            LIMIT ? OFFSET ?
        ";

    $rows = DB::select($searchSql, [...$where->bindings, $fetchLimit, $offset]);
    if (empty($rows)) return ['items' => [], 'total' => $total];

    $reranked   = $this->ranker->rerank($rows, [], '', 'general', 0.0, $preference, []);
    $pagedItems = array_slice($reranked, ($dto->page - 1) * $dto->perPage, $dto->perPage);

    return ['items' => $pagedItems, 'total' => $total];
  }

  private function injectExclusions(ProcessedKeyword $processed, string $suffix): ProcessedKeyword
  {
    return new ProcessedKeyword(
      original: $processed->original,
      booleanQuery: $processed->booleanQuery . $suffix,
      cleanWords: $processed->cleanWords,
      primaryWord: $processed->primaryWord,
      relaxedQueries: array_map(fn($q) => $q . $suffix, $processed->relaxedQueries),
      expandedGroups: $processed->expandedGroups,
      intent: $processed->intent,
      dbExpandedGroups: $processed->dbExpandedGroups,
      hadDbExpansion: $processed->hadDbExpansion,
    );
  }

  // ─────────────────────────────────────────────────────────────────

  public function incrementClickCount(int $entryId, string $language): void
  {
    DB::table('search_indices')
      ->where('entry_id', $entryId)
      ->where('language', $language)
      ->increment('click_count');
  }

  private function getUserKeywords(?int $userId, int $projectId): array
  {
    if ($userId === null) return [];

    static $cache = [];
    $cacheKey = "{$userId}:{$projectId}";
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    $rows = DB::table('user_search_logs')
      ->select('keyword', DB::raw('MAX(searched_at) as last_searched'))
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
      foreach (preg_split('/\s+/', mb_strtolower(trim($row->keyword))) as $word) {
        if (mb_strlen($word) >= 3 && ! is_numeric($word)) {
          $result[$word] = max($result[$word] ?? 0, round($weight, 4));
        }
      }
    }

    arsort($result);
    return $cache[$cacheKey] = array_slice(
      array_map(fn($w, $wt) => ['word' => $w, 'weight' => $wt], array_keys($result), array_values($result)),
      0,
      10
    );
  }
}
