<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataEntryValue extends Model
{
  use SoftDeletes;
  use HasFactory;
  protected $guarded = [];

  public function entry()
  {
    return $this->belongsTo(DataEntry::class, 'data_entry_id');
  }

  public function field()
  {
    return $this->belongsTo(DataTypeField::class, 'data_type_field_id');
  }
}
