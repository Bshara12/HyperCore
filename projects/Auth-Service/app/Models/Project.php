<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'is_active',
        'settings'
    ];

    public function users() {
        return $this->belongsToMany(User::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function project_users()
    {
        return $this->belongsToMany(
            User::class,
            'project_user'
        )->withPivot('role_id');
    }
}
