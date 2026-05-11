<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    public function wishlist()
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForVariant($query, ?int $variantId)
    {
        return $query->where('variant_id', $variantId);
    }

    public function isVariant(): bool
    {
        return ! is_null($this->variant_id);
    }

    protected $guarded = [];
}
