<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class return_requests extends Model
{
  protected $guarded = [];

  public function orderItem(): BelongsTo
  {
    return $this->belongsTo(OrderItem::class);
  }
}
