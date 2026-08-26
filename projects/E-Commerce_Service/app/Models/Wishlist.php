<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static \Database\Factories\WishlistFactory factory($count = null, $state = [])
 * @method static Builder<self> forUser(int $userId)
 * @method static Builder<self> forGuest(string $guestToken)
 * @method static Builder<self> public()
 * @method static Builder<self> private()
 */
class Wishlist extends Model
{
    /** @use HasFactory<\Database\Factories\WishlistFactory> */
    use HasFactory;

    protected $table = 'wishlists';
    protected $guarded = [];

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

    /**
     * @return HasMany<WishlistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class)
            ->orderBy('sort_order');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForGuest(Builder $query, string $guestToken): Builder
    {
        return $query->where('guest_token', $guestToken);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePrivate(Builder $query): Builder
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
}