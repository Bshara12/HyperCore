<?php

namespace App\Domains\E_Commerce\Actions\Cart;

use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Domains\E_Commerce\Support\StockStatusResolver;
use Illuminate\Support\Facades\Cache;

class GetCartAction
{
  public function __construct(
    protected CartRepositoryInterface $cartRepo,
    protected FetchEntriesByIdsAction $fetchEntries,
    protected EnrichEntriesWithPricesAction $enrichPrices,
    protected StockStatusResolver $stockStatusResolver
  ) {}

  public function execute(int $project_id, int $user_id): ?array
  {
    return Cache::remember(
      CacheKeys::cart($user_id, $project_id),
      CacheKeys::TTL_SHORT,
      function () use ($project_id, $user_id) {

        $cart = $this->cartRepo->getOrCreate($project_id, $user_id);
        $cart = $this->cartRepo->loadItems($cart);

        if ($cart->items->isEmpty()) {
          return [
            'cart_id' => $cart->id,
            'items' => [],
            'total' => 0,
            'total_items' => 0,
          ];
        }

        $itemIds = $cart->items->pluck('item_id')->toArray();

        $entries = Cache::remember(
          CacheKeys::entriesByIds($itemIds),
          CacheKeys::TTL_SHORT,
          fn() => $this->fetchEntries->execute($itemIds)
        );

        $enrichedEntries = $this->enrichPrices->execute($entries);
        $entriesMap = collect($enrichedEntries)->keyBy('id');

        $items = $cart->items->map(function ($cartItem) use ($entriesMap) {

          $entry = $entriesMap[$cartItem->item_id] ?? null;
          $price = $entry['final_price'] ?? 0;
          $subtotal = $price * $cartItem->quantity;
          $availableStock = $entry['available_stock'] ?? null;

          return [
            'cart_item_id' => $cartItem->id,
            'item_id' => $cartItem->item_id,
            'quantity' => $cartItem->quantity,
            'original_price' => $entry['original_price'] ?? 0,
            'final_price' => $price,
            'subtotal' => $subtotal,
            'is_offer_applied' => $entry['is_offer_applied'] ?? false,
            'applied_offer_id' => $entry['applied_offer_id'] ?? null,
            'available_stock' => $availableStock,
            'stock_status' => $this->stockStatusResolver->resolve($availableStock, $cartItem->quantity),
            'entry' => $entry,
          ];
        });

        return [
          'cart_id' => $cart->id,
          'items' => $items->values(),
          'total' => $items->sum('subtotal'),
          'total_items' => $items->sum('quantity'),
        ];
      }
    );
  }
}
