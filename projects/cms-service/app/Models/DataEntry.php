<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \App\Models\DataType|null $dataType
 * @property-read \App\Models\Project|null $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DataEntryValue> $values
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 */
class DataEntry extends Model
{
  use HasFactory;
  use SoftDeletes;

  protected $guarded = [];



  protected $casts = [
    'published_at' => 'datetime',
    'scheduled_at' => 'datetime',
  ];

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
  public function dataType(): BelongsTo
  {
    return $this->belongsTo(DataType::class);
  }

  public function values(): HasMany
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
