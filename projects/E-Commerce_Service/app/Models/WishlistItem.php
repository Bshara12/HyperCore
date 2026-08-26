<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static \Database\Factories\WishlistItemFactory factory($count = null, $state = [])
 * @method static Builder<self> ordered()
 * @method static Builder<self> forProduct(int $productId)
 * @method static Builder<self> forVariant(?int $variantId)
 */
class WishlistItem extends Model
{
    /** @use HasFactory<\Database\Factories\WishlistItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Wishlist, $this>
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    protected $table = 'wishlist_items';

    protected $fillable = [
        'wishlist_id',
        'product_id',
        'variant_id',
        'sort_order',
        'added_from_cart',
        'product_snapshot',
        'price_when_added',
        'notify_on_price_drop',
        'notify_on_back_in_stock',
    ];

    protected $casts = [
        'added_from_cart' => 'boolean',
        'notify_on_price_drop' => 'boolean',
        'notify_on_back_in_stock' => 'boolean',
        'product_snapshot' => 'array',
        'price_when_added' => 'decimal:2',
    ];

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForVariant(Builder $query, ?int $variantId): Builder
    {
        return $query->where('variant_id', $variantId);
    }

    public function isVariant(): bool
    {
        return !is_null($this->variant_id);
    }

    protected $guarded = [];
}