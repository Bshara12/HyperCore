<?php

namespace App\Models;

use App\Traits\BelongsToProject as TraitsBelongsToProject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
  use SoftDeletes;
  use HasFactory;
  protected $fillable = ['name', 'owner_id', 'slug', 'supported_languages', 'enabled_modules', 'public_id'];


  protected $casts = [
    'supported_languages' => 'array',
    'enabled_modules' => 'array',
  ];

  // public function users()
  // {
  //   return $this->belongsToMany(User::class, 'project_user');
  // }

  public function getRouteKeyName()
  {
    return 'slug';
  }
  protected static function boot()
  {
    parent::boot();

    static::creating(function ($project) {
      $project->slug = Str::slug($project->name);
    });
  }

  public function payments()
  {
    return $this->hasMany(Payment::class);
  }

  public function collections()
  {
    return $this->hasMany(DataCollection::class);
  }
  public function ratings()
  {
    return $this->morphMany(Rating::class, 'rateable');
  }
  use TraitsBelongsToProject; // يضمن أي عملية create تحوي project_id
}
