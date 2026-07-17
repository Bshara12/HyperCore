<?php

namespace App\Domains\E_Commerce\Support;

class StockStatusResolver
{
  public function resolve(?int $availableStock, int $requestedQuantity): string
  {
    if ($availableStock === null) {
      return 'unknown';
    }

    if ($availableStock === 0) {
      return 'out_of_stock';
    }

    return $availableStock >= $requestedQuantity
      ? 'available'
      : 'insufficient';
  }
}
