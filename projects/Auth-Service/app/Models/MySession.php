<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MySession extends Model
{
    protected $table = 'my_sessions';

    protected $keyType = 'string';
    public $incrementing = false;


    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    protected $casts = [
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime'
    ];
}
