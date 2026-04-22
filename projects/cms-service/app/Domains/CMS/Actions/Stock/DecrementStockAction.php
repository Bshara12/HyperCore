<?php

namespace App\Domains\CMS\Actions\Stock;

use App\Events\SystemLogEvent;
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


        event(new SystemLogEvent(
        module: 'cms',
        eventType: 'update_count',
        userId: $entry->id,
        entityType: 'data',
        entityId:null
      ));

      $countValue->update([
        'value' => $currentStock - $item['quantity']
      ]);
    }
  }
}
