<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataEntryRelation extends Model
{
  protected $fillable = [
    'data_entry_id',
    'related_entry_id',
    'data_type_relation_id',
  ];

  public function entry()
  {
    return $this->belongsTo(DataEntry::class, 'data_entry_id');
  }

  public function relatedEntry()
  {
    return $this->belongsTo(DataEntry::class, 'related_entry_id');
  }

  public function dataTypeRelation()
  {
    return $this->belongsTo(DataTypeRelation::class, 'data_type_relation_id');
  }
}
