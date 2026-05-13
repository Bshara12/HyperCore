<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
  use HasFactory;
  protected $guarded = [];

  public function items(): HasMany
  {
    return $this->hasMany(OrderItem::class);
  }

  // public function user()
  // {
  //   return $this->belongsTo(User::class);
  // }

  // public function project()
  // {
  //   return $this->belongsTo(Project::class);
  // }
  protected $casts = [
    'address' => 'array',
  ];
}
