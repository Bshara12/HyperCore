<?php

namespace App\Domains\E_Commerce\Actions\Pricing;

use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;

class EnrichEntriesWithPricesAction
{
  public function __construct(
    private OfferPriceRepositoryInterface $offerRepo
  ) {}

  public function execute(array $entries): array
  {

    $entryIds = collect($entries)->pluck('id')->toArray();

    // 🔹 1. جلب الأسعار
    $autoPrices = $this->offerRepo->getAutomaticPrices($entryIds);

    // 🔥 جديد: عروض المستخدم (من user_offers)

    $userPrices = $this->offerRepo->getUserPrices($entryIds);


    return collect($entries)->map(function ($entry) use ($autoPrices, $entries, $userPrices) {

      $entryId = $entry['id'];

      // 🔹 normalize values (يدعم الحالتين)
      if (isset($entry['values'][0])) {
        $values = [];
        foreach ($entry['values'] as $item) {
          $values[(int) $item['data_type_field_id']] = $item['value'];
        }
      } else {
        $values = $entry['values'];
      }

      // 🔹 السعر الأصلي
      $originalPrice = (float) ($values[2] ?? $values[7] ?? $values['price'] ?? 0);
      $price = $originalPrice;
      $appliedOffer = null;
      // 🔥 جمع كل الأسعار الممكنة
      $candidates = [];

      if ($autoPrices->has($entryId)) {
        $candidates[] = $autoPrices[$entryId];
      }

      if ($userPrices->has($entryId)) {
        $candidates[] = $userPrices[$entryId];
      }

      // 🔥 اختار أفضل عرض (أقل سعر)
      if (!empty($candidates)) {
        $best = collect($candidates)->sortBy('final_price')->first();

        $price = (float) $best->final_price;
        $appliedOffer = $best->applied_offer_id;
      }

      return [
        'id' => $entryId,

        // 🔥 كل الحقول الديناميكية (title, description, image...)
        ...$values,

        // 🔥 pricing
        'original_price' => $originalPrice,
        'final_price' => $price,
        'is_offer_applied' => $appliedOffer !== null,
        'applied_offer_id' => $appliedOffer,
        'slug' => $entry['slug'],
      ];
    })->toArray();
  }
}
