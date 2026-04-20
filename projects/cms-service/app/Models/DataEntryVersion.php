<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataEntryVersion extends Model
{
  use HasFactory;
  protected $guarded = [];
  protected $casts = [
    'snapshot' => 'array',
  ];

  public function entry()
  {
    return $this->belongsTo(DataEntry::class);
  }
}
