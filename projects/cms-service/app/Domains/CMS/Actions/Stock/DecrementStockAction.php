<?php

namespace App\Domains\CMS\Actions\Stock;

use App\Models\DataEntry;

class DecrementStockAction
{
  public function execute(array $items)
  {
    foreach ($items as $item) {

      $entry = DataEntry::where('id', $item['product_id'])
        ->lockForUpdate()
        ->firstOrFail();

      $countValue = $entry->values()
        ->whereHas('field', fn($q) => $q->where('name', 'count'))
        ->first();

      $currentStock = (int) $countValue->value;

      if ($currentStock < $item['quantity']) {
        throw new \Exception("Not enough stock for product {$entry->id}");
      }

      $countValue->update([
        'value' => $currentStock - $item['quantity']
      ]);
    }
  }
}
