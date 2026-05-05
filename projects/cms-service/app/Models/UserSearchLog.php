<?php

namespace App\Domains\Search\Models;

use Illuminate\Database\Eloquent\Model;

class UserSearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'keyword',
        'language',
        'detected_intent',
        'intent_confidence',
        'results_count',
        'session_id',
        'searched_at',
    ];

    protected $casts = [
        'searched_at' => 'datetime',
    ];

    public function clicks()
    {
        return $this->hasMany(UserClickLog::class, 'search_log_id');
    }
}
