<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataType extends Model
{
  use SoftDeletes;
  use HasFactory;
  
  protected $fillable = [
    'project_id',
    'name',
    'slug',
    'description',
    'is_active',
    'settings'
  ];

  protected $casts = [
    'settings' => 'array',
    'is_active' => 'boolean',
  ];

  // public function resolveRouteBinding($value, $field = null)
  // {
  //   $project = app()->bound('currentProject') ? app('currentProject') : null;

  //   if (!$project || !isset($project->id)) {
  //     throw (new ModelNotFoundException)->setModel(static::class);
  //   }

  //   $field = $field ?: 'id';

  //   return $this->newQuery()
  //     ->where($field, $value)
  //     ->where('project_id', $project->id)
  //     ->firstOrFail();
  // }


  public function project()
  {
    return $this->belongsTo(Project::class);
  }

  public function collections()
  {
    return $this->hasMany(DataCollection::class);
  }

  public function fields()
  {
    return $this->hasMany(DataTypeField::class);
  }

  public function entries()
  {
    return $this->hasMany(DataEntry::class);
  }

  public function relations()
  {
    return $this->hasMany(DataTypeRelation::class);
  }
  public function relatedRelations()
  {
    return $this->hasMany(DataTypeRelation::class, 'related_data_type_id');
  }
}
