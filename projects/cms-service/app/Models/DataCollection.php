<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataCollection extends Model
{
  use HasFactory;
  /*
   | "items" is deliberately absent from both lists below. There is no items
   | column on data_collections — items live in data_collection_items and are
   | reached through the items() relation. Declaring it as a fillable array
   | cast made $collection['items'] = ... land in $attributes as JSON, which
   | shadowed the relation and only rendered correctly by accident.
   | Read paths use setRelation('items', ...) instead.
   */
  protected $fillable = [
    'project_id',
    'data_type_id',
    'name',
    'slug',
    'type',
    'conditions',
    'conditions_logic',
    'description',
    'is_active',
    'is_offer',
    'settings',
  ];

  protected $casts = [
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
    // return $this->collectionItems()->orderBy('sort_order');
    return $this->items()
      ->orderBy('sort_order');
  }

  public function items()
  {
    return $this->hasMany(DataCollectionItem::class, 'collection_id');
  }
}
