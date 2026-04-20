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
        'is_verified'
    ];
}
