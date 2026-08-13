<?php

namespace App\Domains\Search\Support;

use App\Domains\Search\DTOs\UserPreferenceDTO;

class SearchResultRanker
{

  private const TERM_AFFINITY_WEIGHT = 6.0;
  private const AFFINITY_WEIGHT = 2.0;

  private const INTENT_SLUGS = [
    'product' => ['products', 'product', 'items', 'goods'],
    'article' => ['articles', 'article', 'posts', 'blog', 'news'],
    'service' => ['services', 'service', 'booking', 'appointments'],
    'buy'     => ['products', 'product', 'items', 'goods'],
    'repair'  => ['services', 'service', 'booking', 'appointments'],
    'learn'   => ['articles', 'article', 'posts', 'blog', 'news'],
    'compare' => ['articles', 'article', 'posts', 'blog', 'products', 'product'],
  ];

  public function __construct(
    private KeywordTokenizer $tokenizer,
  ) {}

  public function rerank(
    array             $rows,
    array             $cleanWords,
    string            $phraseQuery,
    string            $intent,
    float             $intentConf,
    UserPreferenceDTO $preference,
    array             $userKeywords = []
  ): array {

    if (empty($rows)) {
      return [];
    }

    $phraseQueryLower = mb_strtolower($phraseQuery, 'UTF-8');
    $numberWords      = array_filter($cleanWords, fn($w) => is_numeric($w));
    $intentSlugs      = $this->getSlugs($intent, $intentConf);

    foreach ($rows as $row) {
      $row->final_score = $this->computeScore(
        row: $row,
        cleanWords: $cleanWords,
        phraseQueryLower: $phraseQueryLower,
        numberWords: $numberWords,
        intentSlugs: $intentSlugs,
        intentConf: $intentConf,
        preference: $preference,
        userKeywords: $userKeywords,
      );
    }

    usort($rows, fn($a, $b) => $b->final_score <=> $a->final_score);

    return $rows;
  }


  private function computeScore(
    object            $row,
    array             $cleanWords,
    string            $phraseQueryLower,
    array             $numberWords,
    array             $intentSlugs,
    float             $intentConf,
    UserPreferenceDTO $preference,
    array             $userKeywords,
  ): float {

    $title   = mb_strtolower($row->title   ?? '', 'UTF-8');
    $content = mb_strtolower($row->content ?? '', 'UTF-8');
    $slug    = $row->data_type_slug ?? '';


    $score = (float) ($row->fulltext_score ?? 0) * 3.0;

    if (!empty($phraseQueryLower)) {
      if (str_contains($title, $phraseQueryLower)) {
        $score += 8.0;
      } elseif (str_contains($content, $phraseQueryLower)) {
        $score += 3.0;
      }
    }

    foreach ($cleanWords as $word) {
      $w = mb_strtolower($word, 'UTF-8');
      if (str_contains($title, $w)) {
        $score += 2.0;
      } elseif (str_contains($content, $w)) {
        $score += 0.5;
      }
    }


    foreach ($numberWords as $num) {
      if (str_contains($title, (string) $num)) {
        $score += 5.0;
      } else {
        $score -= 1.0; // عقوبة: الرقم المطلوب غير موجود
      }
    }


    if (!empty($cleanWords[0])) {
      $firstWord = mb_strtolower($cleanWords[0], 'UTF-8');
      $pos = mb_strpos($title, $firstWord, 0, 'UTF-8');
      if ($pos !== false) {
        $score += 1.5 / ($pos + 2);
      }
    }


    $clickCount      = max(0, (int) ($row->click_count   ?? 0));
    $viewCount       = max(0, (int) ($row->view_count    ?? 0));
    $popularityScore = max(0, (float) ($row->popularity_score ?? 0));
    $ctrScore        = max(0, (float) ($row->ctr_score    ?? 0));
    $freshnessScore  = max(0, (float) ($row->freshness_score ?? 0));

    // Click popularity: LOG لتخفيف هيمنة الأرقام الكبيرة
    $score += log($clickCount + 1) * 2.5;

    $score += log($viewCount + 1) * 1.5;
    $score += $popularityScore * 3.0;
    $score += $ctrScore * 4.0;
    $score += $freshnessScore * 2.0;

    if (!empty($intentSlugs) && in_array($slug, $intentSlugs, true)) {
      $score += $intentConf * 5.0;
    }


    if (!empty($preference->termAffinities)) {
      $rowTokens = array_flip($this->tokenizer->tokenize($title . ' ' . $content));

      foreach ($preference->termAffinities as $term => $termAffinity) {
        if (isset($rowTokens[$term])) {
          $score += $termAffinity * self::TERM_AFFINITY_WEIGHT;
        }
      }
    }

    $dataTypeAffinity = $preference->affinityFor((int) ($row->data_type_id ?? 0));
    if ($dataTypeAffinity > 0.0) {
      $score += $dataTypeAffinity * self::AFFINITY_WEIGHT;
    }

    foreach ($userKeywords as $kw) {
      $kwLower = mb_strtolower($kw['word'], 'UTF-8');
      if (str_contains($title, $kwLower)) {
        $score += $kw['weight'] * 2.0;
        break;
      }
    }

    return round($score, 4);
  }


  private function getSlugs(string $intent, float $confidence): array
  {
    if ($confidence < 0.3) return [];
    return self::INTENT_SLUGS[$intent] ?? [];
  }
}
