<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ServiceSession extends Model
{
    protected $table = 'service_sessions';

    protected $keyType = 'string';
    public $incrementing = false;


    protected $fillable = [
        'service_client_id',
        'client_id'
    ];

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
