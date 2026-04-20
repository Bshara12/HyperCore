<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;

class EnrichOrderItemsAction
{
  public function __construct(
    protected EnrichEntriesWithPricesAction $pricingAction
  ) {}

  // public function execute($orders)
  // {
  //   foreach ($orders as $order) {

  //     $entries = collect($order->items)->map(function ($item) {
  //       return [
  //         'id' => $item->product_id,
  //         'quantity' => $item->quantity
  //       ];
  //     })->toArray();

  //     // 🔥 استدعاء pricing logic
  //     $enriched = $this->pricingAction->execute($entries);
  //     // // 🔥 ربط النتائج مع items
  //     // foreach ($order->items as $index => $item) {
  //     //   $item->entry = $enriched[$index] ?? null;
  //     // }
  //     $enrichedMap = collect($enriched)->keyBy('id');

  //     foreach ($order->items as $item) {
  //       $item->entry = $enrichedMap[$item->product_id] ?? null;
  //     }
  //   }

  //   return $orders;
  // }

  public function execute($orders)
  {
    foreach ($orders as $order) {

      // 🔥 1. جهز entries بالشكل المطلوب
      // $entries = collect($order->items)->map(function ($item) {
      //   return [
      //     'id' => $item->product_id,

      //     // ✅ أهم سطر
      //     'values' => [
      //       'price' => $item->price
      //     ]
      //   ];
      // })->toArray();

      $itemIds = collect($order->items)->pluck('product_id')->toArray();

      // 🔥 نفس الكارت
      $entries = app(FetchEntriesByIdsAction::class)
        ->execute($itemIds);

      // 🔥 2. استدعاء pricing
      $enriched = $this->pricingAction->execute($entries);

      // 🔥 3. ربط النتائج
      $enrichedMap = collect($enriched)->keyBy('id');

      foreach ($order->items as $item) {
        $item->entry = $enrichedMap[$item->product_id] ?? null;
      }
    }

    return $orders;
  }
}
