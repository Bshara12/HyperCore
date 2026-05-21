<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\DataTypeField;

/**
 * @property DataTypeField|null $field
 */
class DataEntryValue extends Model
{
  use HasFactory;
  use SoftDeletes;

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
