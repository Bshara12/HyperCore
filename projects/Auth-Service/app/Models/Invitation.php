<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $table = 'project_invitations';

    protected $fillable = [
        'project_id',
        'role_id',
        'email',
        'otp_code',
        'otp_expires_at',
        'locked_until',
        'is_verified',
    ];

    protected $casts = [
    'otp_expires_at' => 'datetime',
    'locked_until'   => 'datetime',
    'is_verified'    => 'boolean', // هذا السطر سيحل مشكلة الـ 1 و true
];
}
