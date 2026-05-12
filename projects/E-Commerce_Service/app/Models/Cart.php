<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
  use HasFactory;
  protected $guarded = [];

  public function items()
  {
    return $this->hasMany(CartItem::class);
  }

  // حساب السعر الكلي للسلة
  public function getTotalAttribute()
  {
    return $this->items->sum('subtotal');
  }
}
