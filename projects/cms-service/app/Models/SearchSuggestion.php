<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SearchSuggestion extends Model
{
    protected $table = 'search_suggestions';

    protected $fillable = [
        'project_id',
        'keyword',
        'language',
        'normalized_keyword',
        'search_count',
        'click_count',
        'score',
        'last_searched_at',
    ];

    protected $casts = [
        'last_searched_at' => 'datetime',
        'score' => 'float',
    ];

    /**
     * حساب الـ score بناءً على search_count و click_count و recency
     *
     * الصيغة:
     *   score = log(search_count + 1) * 1.0
     *         + log(click_count  + 1) * 2.0   ← click أكثر أهمية من search
     *         + recency_bonus                  ← البحث الحديث أهم
     */
    public static function calculateScore(
        int $searchCount,
        int $clickCount,
        ?Carbon $lastSearchedAt = null
    ): float {
        $searchScore = log($searchCount + 1, 10);
        $clickScore = log($clickCount + 1, 10) * 2.0;

        // Recency bonus: كلما كان أحدث → bonus أكبر (max 1.0)
        $recencyBonus = 0.0;
        if ($lastSearchedAt) {
            $daysAgo = now()->diffInDays($lastSearchedAt);
            $recencyBonus = max(0, 1.0 - ($daysAgo / 30));
        }

        return round($searchScore + $clickScore + $recencyBonus, 4);
    }
}
