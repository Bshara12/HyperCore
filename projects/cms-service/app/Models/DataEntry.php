<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DataEntry extends Model
{
  use SoftDeletes;
  use HasFactory;
  protected $guarded = [];

  // public function resolveRouteBinding($value, $field = null)
  // {
  //   $project = app('currentProject', null);

  //   if (!$project || !isset($project->id)) {
  //     throw (new ModelNotFoundException)->setModel(static::class);
  //   }

  //   $field = $field ?: 'slug';

  //   return $this->newQuery()
  //     ->where($field, $value)
  //     ->where('project_id', $project->id)
  //     ->firstOrFail();
  // }


  // test*************************
  public function project()
  {
    return $this->belongsTo(Project::class);
  }
  // ******************
  public function dataType()
  {
    return $this->belongsTo(DataType::class);
  }

  public function values()
  {
    return $this->hasMany(DataEntryValue::class, 'data_entry_id');
  }

  public function versions()
  {
    return $this->hasMany(DataEntryVersion::class);
  }

  public function relations()
  {
    return $this->hasMany(DataEntryRelation::class);
  }

  public function ratings()
  {
    return $this->morphMany(Rating::class, 'rateable');
  }
}
