<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;

class Wishlist extends Model
{
  use HasFactory;
  protected $table = 'wishlists';

  protected $fillable = [
    'user_id',
    'guest_token',
    'name',
    'is_default',
    'visibility',
    'share_token',
    'is_shareable',
  ];

  protected $casts = [
    'is_default' => 'boolean',
    'is_shareable' => 'boolean',
  ];

  public function items(): HasMany
  {
    return $this->hasMany(WishlistItem::class)
      ->orderBy('sort_order');
  }

  public function scopeForUser($query, int $userId)
  {
    return $query->where('user_id', $userId);
  }

  public function scopeForGuest($query, string $guestToken)
  {
    return $query->where('guest_token', $guestToken);
  }

  public function scopePublic($query)
  {
    return $query->where('visibility', 'public');
  }

  public function scopePrivate($query)
  {
    return $query->where('visibility', 'private');
  }

  public function isOwnedBy(int $userId): bool
  {
    return (int) $this->user_id === $userId;
  }

  public function isGuestOwnedBy(string $guestToken): bool
  {
    return $this->guest_token === $guestToken;
  }

  public function isPublic(): bool
  {
    return $this->visibility === 'public';
  }

  public function hasProduct(int $productId, ?int $variantId = null): bool
  {
    return $this->items()
      ->where('product_id', $productId)
      ->where('variant_id', $variantId)
      ->exists();
  }
  protected $guarded = [];
}
