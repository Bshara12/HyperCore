<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PopularSearch extends Model
{
    protected $table = 'popular_searches';

    protected $fillable = [
        'project_id', 'keyword', 'language', 'normalized_keyword',
        'count_24h', 'count_7d', 'count_30d', 'count_all_time',
        'click_count', 'trending_score', 'alltime_score',
        'last_searched_at', 'last_computed_at',
    ];

    protected $casts = [
        'last_searched_at' => 'datetime',
        'last_computed_at' => 'datetime',
        'trending_score' => 'float',
        'alltime_score' => 'float',
    ];

    // ─────────────────────────────────────────────────────────────────

    public static function calculateTrendingScore(
        int $count24h,
        int $count7d,
        int $count30d,
        ?Carbon $lastSearchedAt = null
    ): float {
        // ─── تأكد من عدم وجود قيم سالبة ─────────────────────────────
        $count24h = max(0, $count24h);
        $count7d = max(0, $count7d);
        $count30d = max(0, $count30d);

        $weightedCount = ($count24h * 4) + ($count7d * 2) + ($count30d * 1);

        if ($weightedCount === 0) {
            return 0.0;  // لا بيانات = score صفر (لن يظهر أبداً في أعلى القائمة)
        }

        $ageHours = $lastSearchedAt
            ? max(0, now()->diffInHours($lastSearchedAt))
            : 720;

        // ─── +2 يمنع division by zero عندما ageHours = 0 ──────────
        $denominator = pow($ageHours + 2, 1.5);

        // ─── تأكد من عدم division by zero ────────────────────────────
        if ($denominator <= 0) {
            return 0.0;
        }

        $score = $weightedCount / $denominator;

        // ─── تأكد من أن النتيجة رقم حقيقي ───────────────────────────
        return is_finite($score) ? round($score, 4) : 0.0;
    }

    // ─────────────────────────────────────────────────────────────────

    public static function calculateAlltimeScore(
        int $countAllTime,
        int $clickCount,
        ?Carbon $lastSearchedAt = null
    ): float {
        $countAllTime = max(0, $countAllTime);
        $clickCount = max(0, $clickCount);

        // log10(0+1) = 0 → لا مشكلة
        $searchScore = log10($countAllTime + 1);
        $clickScore = log10($clickCount + 1) * 1.5;

        $recencyBonus = 0.0;
        if ($lastSearchedAt !== null) {
            $daysSince = max(0, now()->diffInDays($lastSearchedAt));
            $recencyBonus = max(0.0, 1.0 - ($daysSince / 90.0));
        }

        $score = $searchScore + $clickScore + $recencyBonus;

        return is_finite($score) ? round($score, 4) : 0.0;
    }

    // ─────────────────────────────────────────────────────────────────

    public static function detectTrend(int $count24h, int $count7d): string
    {
        $count24h = max(0, $count24h);
        $count7d = max(0, $count7d);

        if ($count7d === 0) {
            return $count24h > 0 ? 'rising' : 'stable';
        }

        $dailyAvg7d = $count7d / 7.0;
        // max(0.1) يمنع division by near-zero
        $ratio = $count24h / max($dailyAvg7d, 0.1);

        return match (true) {
            $ratio >= 2.0 => 'rising',
            $ratio <= 0.5 => 'falling',
            default => 'stable',
        };
    }
}
