<?php

namespace App\Domains\Search\Support;

/**
 * يُحدد ترتيب الـ fallback لكل window
 *
 * المنطق:
 *   24h → 7d → 30d → all
 *   7d  → 30d → all
 *   30d → all
 *   all → (لا fallback)
 */
final class WindowFallbackChain
{
    private const CHAINS = [
        '24h' => ['24h', '7d', '30d', 'all'],
        '7d'  => ['7d', '30d', 'all'],
        '30d' => ['30d', 'all'],
        'all' => ['all'],
    ];

    private const COUNT_COLUMNS = [
        '24h' => 'count_24h',
        '7d'  => 'count_7d',
        '30d' => 'count_30d',
        'all' => 'count_all_time',
    ];

    private const SCORE_COLUMNS = [
        '24h' => 'trending_score',
        '7d'  => 'trending_score',
        '30d' => 'alltime_score',
        'all' => 'alltime_score',
    ];

    /**
     * إرجاع ترتيب الـ fallback للـ window المطلوب
     *
     * @return string[]
     */
    public static function getChain(string $window): array
    {
        return self::CHAINS[$window] ?? self::CHAINS['all'];
    }

    public static function getCountColumn(string $window): string
    {
        return self::COUNT_COLUMNS[$window] ?? 'count_all_time';
    }

    public static function getScoreColumn(string $window): string
    {
        return self::SCORE_COLUMNS[$window] ?? 'alltime_score';
    }

    public static function isValid(string $window): bool
    {
        return isset(self::CHAINS[$window]);
    }
}