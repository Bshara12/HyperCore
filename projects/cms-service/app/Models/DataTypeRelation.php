<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataTypeRelation extends Model
{

  protected $table = 'data_type_relations';
  protected $fillable = [
    'data_type_id',
    'related_data_type_id',
    'relation_type',
    'relation_name',
    'pivot_table',
  ];

  public function dataType()
  {
    return $this->belongsTo(DataType::class);
  }

  public function relatedDataType()
  {
    return $this->belongsTo(DataType::class, 'related_data_type_id');
  }
}
