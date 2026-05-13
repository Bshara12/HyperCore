<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_public
 * @property int $owner_id
 * @property \Illuminate\Database\Eloquent\Relations\Pivot $pivot
 */
class Project extends Model
{
  protected $fillable = [
    'owner_id',
    'name',
    'slug',
    'is_active',
    'settings',
  ];

  public function users()
  {
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
