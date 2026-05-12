<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;

class CalculateCartPricingAction
{
    public function __construct(
        protected FetchEntriesByIdsAction $fetchEntries,
        protected EnrichEntriesWithPricesAction $enrichPrices,
    ) {}

    public function execute($cart)
    {
        $itemIds = $cart->items->pluck('item_id')->toArray();

        $entries = $this->fetchEntries->execute($itemIds);
        $enriched = $this->enrichPrices->execute($entries);

        $map = collect($enriched)->keyBy('id');

        $items = [];
        $total = 0;

        foreach ($cart->items as $item) {
            $entry = $map[$item->item_id];

            $price = $entry['final_price'];
            $subtotal = $price * $item->quantity;

            $total += $subtotal;

            $items[] = [
                'product_id' => $item->item_id,
                'title' => $entry[0] ?? 'N/A',
                'slug' => $entry['slug'],
                'quantity' => $item->quantity,
                'price' => $price,
                'count' => $entry[3] ?? null,
                'total' => $subtotal,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
