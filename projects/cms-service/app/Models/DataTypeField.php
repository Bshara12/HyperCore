<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataTypeField extends Model
{
  use SoftDeletes;
  use HasFactory;

  protected $fillable = [
    'data_type_id',
    'name',
    'type',
    'required',
    'translatable',
    'validation_rules',
    'settings',
    'sort_order',
  ];

  protected $casts = [
    'validation_rules' => 'array',
    'settings' => 'array',
    'required' => 'boolean',
    'translatable' => 'boolean',
    'sort_order' => 'integer',
  ];

  public function dataType()
  {
    return $this->belongsTo(DataType::class);
  }

  public function values()
  {
    return $this->hasMany(DataEntryValue::class, 'data_type_field_id');
  }
}
