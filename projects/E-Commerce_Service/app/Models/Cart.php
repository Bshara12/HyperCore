<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
  protected $guarded = [];

  public function items(): HasMany
  {
    return $this->hasMany(CartItem::class);
  }

  // حساب السعر الكلي للسلة
  public function getTotalAttribute()
  {
    return $this->items->sum('subtotal');
  }
}
