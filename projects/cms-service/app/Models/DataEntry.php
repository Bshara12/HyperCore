<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Support\CurrentProject;

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

  /**
   * Entry slugs are unique per project only (see the project_id + slug unique
   * index), so the current project has to be part of the lookup - otherwise a
   * request scoped to project B can read/delete project A's entry.
   */
  public function resolveRouteBinding($value, $field = null)
  {
    $project = CurrentProject::resolve();

    if (! $project) {
      throw (new ModelNotFoundException)->setModel(static::class, [$value]);
    }

    return $this->resolveRouteBindingQuery($this, $value, $field)
      ->where('project_id', $project->id)
      ->firstOrFail();
  }

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
