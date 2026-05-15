<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
  use HasFactory, SoftDeletes;

  protected $guarded = [];

  protected $casts = [
    'benefit_config' => 'array',
    'is_active' => 'boolean',
    'is_code_offer' => 'boolean',
    'start_at' => 'datetime',
    'end_at' => 'datetime',
  ];

  public function offer_price()
  {
    return $this->hasMany(OfferPrice::class, 'applied_offer_id', 'id');
  }
}
