<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];

    /**
     * 🔴 تصحيح: كان الترتيب (user_id, role_id) — معكوس!
     * بما إنه هذا موديل Role، مفتاحه بجدول الربط (role_id) لازم يكون أولاً
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id')
            ->withTimestamps()
            ->withPivot('project_id');
    }

    public function permessions()
    {
        return $this->belongsToMany(Permession::class)->withTimestamps();
    }
}
