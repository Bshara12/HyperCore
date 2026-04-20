<?php

namespace App\Domains\E_Commerce\Actions\Cart;

use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Services\CMS\CMSApiClient;

class GetCartAction
{
  public function __construct(
    protected CartRepositoryInterface       $cartRepo,
    protected FetchEntriesByIdsAction       $fetchEntries,
    protected EnrichEntriesWithPricesAction $enrichPrices,
    protected CMSApiClient                  $cms
  ) {}

  public function execute(int $project_id, int $user_id): array
  {
    $cart = $this->cartRepo->getOrCreate($project_id, $user_id);
    $cart = $this->cartRepo->loadItems($cart);

    if ($cart->items->isEmpty()) {
      return [
        'cart_id'     => $cart->id,
        'items'       => [],
        'total'       => 0,
        'total_items' => 0,
      ];
      
    }

    // 1 — جلب item_ids من السلة
    $itemIds = $cart->items->pluck('item_id')->toArray();

    // 2 — جلب بيانات العناصر من CMS
    $entries = $this->fetchEntries->execute($itemIds);

    // 3 — إضافة أفضل سعر لكل عنصر (عروض تلقائية + عروض المستخدم)
    $enrichedEntries = $this->enrichPrices->execute($entries);

    // 4 — تحويل إلى map لسهولة الوصول
    $entriesMap = collect($enrichedEntries)->keyBy('id');

    // 5 — جلب حالة المخزون لكل العناصر دفعة واحدة
    $stockMap = $this->cms->getStockStatus($itemIds);

    // 6 — بناء الـ response مع السعر الحالي
    $items = $cart->items->map(function ($cartItem) use ($entriesMap, $stockMap) {
      $entry    = $entriesMap[$cartItem->item_id] ?? null;
      $price    = $entry['final_price']    ?? 0;
      $subtotal = $price * $cartItem->quantity;

      // حالة المخزون
      $stock          = $stockMap[$cartItem->item_id] ?? null;
      $availableStock = $stock['available'] ?? null;
      $stockStatus    = $this->resolveStockStatus($availableStock, $cartItem->quantity);

      return [
        'cart_item_id'     => $cartItem->id,
        'item_id'          => $cartItem->item_id,
        'quantity'         => $cartItem->quantity,
        'original_price'   => $entry['original_price']   ?? 0,
        'final_price'      => $price,
        'subtotal'         => $subtotal,
        'is_offer_applied' => $entry['is_offer_applied'] ?? false,
        'applied_offer_id' => $entry['applied_offer_id'] ?? null,
        'available_stock' => $availableStock,
        'stock_status'    => $stockStatus,  // available | insufficient | out_of_stock
        'entry'            => $entry,
      ];
    });

    return [
      'cart_id'     => $cart->id,
      'items'       => $items->values(),
      'total'       => $items->sum('subtotal'),
      'total_items' => $items->sum('quantity'),
    ];
  }

  private function resolveStockStatus(?int $available, int $requested): string
  {
    if ($available === null) return 'available';  // لا يوجد stock tracking لهذا العنصر
    if ($available <= 0)     return 'out_of_stock';
    if ($available < $requested) return 'insufficient';
    return 'available';
  }
}
