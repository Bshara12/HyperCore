<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataCollection extends Model
{
  protected $fillable = [
    'project_id',
    'data_type_id',
    'name',
    'slug',
    'type',
    'items',
    'conditions',
    'conditions_logic',
    'description',
    'is_active',
    'is_offer',
    'settings',
  ];

  protected $casts = [
    'items' => 'array',
    'conditions' => 'array',
    'settings' => 'array',
    'is_active' => 'boolean',
    'is_offer' => 'boolean',
  ];

  public function project()
  {
    return $this->belongsTo(Project::class);
  }

  public function dataType()
  {
    return $this->belongsTo(DataType::class);
  }

  public function orderedItems()
  {
    return $this->collectionItems()->orderBy('sort_order');
  }

  public function items()
  {
    return $this->hasMany(DataCollectionItem::class, 'collection_id');
  }
}
