<?php

namespace App\Domains\Search\Models;

use Illuminate\Database\Eloquent\Model;

class UserClickLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'search_log_id',
        'entry_id',
        'data_type_id',
        'result_position',
        'session_id',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function searchLog()
    {
        return $this->belongsTo(UserSearchLog::class, 'search_log_id');
    }
}