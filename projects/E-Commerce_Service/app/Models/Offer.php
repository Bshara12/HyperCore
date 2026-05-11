<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'benefit_config' => 'array',
        'is_active' => 'boolean',
        'is_code_offer' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
