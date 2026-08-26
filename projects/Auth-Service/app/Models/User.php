<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_verified',
        'otp_code',
        'otp_expires_at',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'otp_expires_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps()
            ->withPivot('project_id'); // ✅ جديد
    }

    /**
     * ✅ جديد: أدوار المستخدم ضمن مشروع محدد، أو العامة (project_id = null) افتراضياً
     * متاحة لأي كود مستقبلي يحتاجها، بدون ما تغيّر شكل استجابة myProfile/profile الحالية
     */
    public function rolesForProject(?int $projectId = null)
    {
        return $this->roles()->wherePivot('project_id', $projectId);
    }

    public function permessions()
    {
        return $this->roles()
            ->with('permessions')
            ->get()
            ->pluck('permessions')
            ->flatten()
            ->unique('id');
    }
}
