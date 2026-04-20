<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOffer extends Model
{
  protected $guarded = [];

  protected $casts = [
    'start_at' => 'datetime',
    'end_at' => 'datetime',
  ];

  public function offer()
  {
    return $this->belongsTo(Offer::class);
  }
}
