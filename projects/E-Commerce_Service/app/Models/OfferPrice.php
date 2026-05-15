<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferPrice extends Model
{
  use HasFactory;
  protected $guarded = [];

  protected $casts = [
    'original_price' => 'decimal:2',
    'final_price' => 'decimal:2',
    'is_applied' => 'boolean',
    'is_code_price' => 'boolean',
    // 'valid_until' => 'datetime',
  ];

  public function offer()
  {
    return $this->belongsTo(Offer::class, 'applied_offer_id', 'id');
  }
}
