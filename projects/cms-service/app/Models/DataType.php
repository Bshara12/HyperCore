<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\DataTypeField;
use App\Support\CurrentProject;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DataTypeField> $fields
 */
class DataType extends Model
{
  use HasFactory;
  use SoftDeletes;

  protected $fillable = [
    'project_id',
    'name',
    'slug',
    'description',
    'is_active',
    'settings',
  ];

  protected $casts = [
    'settings' => 'array',
    'is_active' => 'boolean',
  ];

  /**
   * Data type slugs are only unique per project, so the current project must be
   * part of the lookup - otherwise "products" of project A can be bound while
   * the request is scoped to project B, and the created entry ends up with
   * project B's project_id and project A's data_type_id.
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

  public function project()
  {
    return $this->belongsTo(Project::class);
  }

  public function collections()
  {
    return $this->hasMany(DataCollection::class);
  }

  public function fields(): HasMany
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

  public function getRouteKeyName()
  {
    return 'slug';
  }
}
