<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\SearchResultDTO;
use App\Domains\Search\DTOs\SearchResultItemDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Support\KeywordProcessor;
use App\Domains\Search\Support\ProcessedKeyword;
use App\Domains\Search\Support\UserPreferenceAnalyzer;

class SearchEntriesAction
{
  public function __construct(
    private SearchRepositoryInterface $repository,
    private KeywordProcessor          $processor,
    private UserPreferenceAnalyzer    $preferenceAnalyzer,
    private LogSearchAction           $logSearchAction,
    private SyncSuggestionAction      $syncSuggestion,   // ← إضافة

  ) {}

  // ─────────────────────────────────────────────────────────────────

  public function execute(SearchQueryDTO $dto): SearchResultDTO
  {
    // ─── 1. معالجة الكلمات ────────────────────────────────────────
    $processed = $this->processor->process($dto->keyword);

    // ─── 2. تحليل تفضيلات المستخدم ───────────────────────────────
    $preference = $this->preferenceAnalyzer->analyze(
      projectId: $dto->projectId,
      userId: $dto->userId,
      sessionId: $dto->sessionId,
    );

    // ─── 3. تنفيذ البحث مع الـ preference ───────────────────────
    $result = $this->repository->search($dto, $processed, $preference);

    $total = $result['total'];
    $rows  = $result['items'];

    $items = array_map(
      fn($row) => $this->mapToDTO($row, $processed),
      $rows
    );

    // ─── 4. تسجيل عملية البحث (async-safe) ──────────────────────
    $this->logSearch($dto, $processed, $preference, $total);


  $this->syncSuggestion->execute(
        projectId: $dto->projectId,
        keyword:   $dto->keyword,
        language:  $dto->language,
    );


    return new SearchResultDTO(
      keyword: $dto->keyword,
      total: $total,
      page: $dto->page,
      perPage: $dto->perPage,
      lastPage: $total > 0 ? (int) ceil($total / $dto->perPage) : 1,
      items: $items,
    );
  }

  // ─────────────────────────────────────────────────────────────────

  private function logSearch(
    SearchQueryDTO   $dto,
    ProcessedKeyword $processed,
    UserPreferenceDTO $preference,
    int              $total
  ): void {
    try {
      $this->logSearchAction->execute(new LogSearchDTO(
        projectId: $dto->projectId,
        keyword: $dto->keyword,
        language: $dto->language,
        resultsCount: $total,
        detectedIntent: $processed->intent['intent'],
        intentConfidence: $processed->intent['confidence'],
        userId: $dto->userId,
        sessionId: $dto->sessionId,
      ));
    } catch (\Throwable) {
      // الـ logging لا يجب أن يوقف البحث
    }
  }

  // ─────────────────────────────────────────────────────────────────

  private function mapToDTO(object $row, ProcessedKeyword $processed): SearchResultItemDTO
  {
    $snippet = $this->generateSnippet($row->content ?? '', $processed->cleanWords);

    return new SearchResultItemDTO(
      entryId: (int) $row->entry_id,
      dataTypeId: (int) $row->data_type_id,
      projectId: (int) $row->project_id,
      language: $row->language,
      title: $this->highlightText($row->title ?? '', $processed->cleanWords),
      snippet: $this->highlightText($snippet, $processed->cleanWords),
      status: $row->status,
      score: round((float) ($row->weighted_score ?? 0), 4),
      publishedAt: $row->published_at,
    );
  }

  // ─────────────────────────────────────────────────────────────────
  // Snippet + Highlighting (لم تتغير)
  // ─────────────────────────────────────────────────────────────────

  private function generateSnippet(string $content, array $words, int $contextBefore = 60, int $contextAfter = 100): string
  {
    if (empty($content)) return '';
    $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (empty($plain)) return '';
    $matchPosition = $this->findFirstMatchPosition($plain, $words);
    if ($matchPosition === null) return $this->buildFallbackSnippet($plain, 160);
    return $this->buildCenteredSnippet($plain, $matchPosition, $contextBefore, $contextAfter);
  }

  private function findFirstMatchPosition(string $text, array $words): ?int
  {
    $earliest = null;
    foreach ($words as $word) {
      if (empty($word)) continue;
      $pos = mb_stripos($text, $word, 0, 'UTF-8');
      if ($pos !== false && ($earliest === null || $pos < $earliest)) {
        $earliest = $pos;
      }
    }
    return $earliest;
  }

  private function buildCenteredSnippet(string $text, int $matchPosition, int $contextBefore, int $contextAfter): string
  {
    $totalLength = mb_strlen($text, 'UTF-8');
    $start  = max(0, $matchPosition - $contextBefore);
    $end    = min($totalLength, $matchPosition + $contextAfter);
    $snippet = mb_substr($text, $start, $end - $start, 'UTF-8');
    return ($start > 0 ? '...' : '') . trim($snippet) . ($end < $totalLength ? '...' : '');
  }

  private function buildFallbackSnippet(string $text, int $length): string
  {
    return mb_strlen($text, 'UTF-8') <= $length ? $text : mb_substr($text, 0, $length, 'UTF-8') . '...';
  }

  private function highlightText(string $text, array $words): string
  {
    if (empty($text) || empty($words)) return $text;
    $sorted = $words;
    usort($sorted, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
    foreach ($sorted as $word) {
      if (mb_strlen($word, 'UTF-8') < 2) continue;
      $text = $this->highlightWord($text, $word);
    }
    return $text;
  }

  private function highlightWord(string $text, string $word): string
  {
    $escaped     = preg_quote($word, '/');
    $pattern     = '/(?<!\*\*)(' . $escaped . ')(?!\*\*)/iu';
    $highlighted = preg_replace($pattern, '**$1**', $text);
    return $highlighted ?? $text;
  }
}
