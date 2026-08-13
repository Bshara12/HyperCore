<?php

use App\Domains\E_Commerce\Support\StockStatusResolver;

beforeEach(function () {
  $this->resolver = new StockStatusResolver();
});

it('resolves stock status correctly for all scenarios', function (?int $availableStock, int $requestedQuantity, string $expectedStatus) {

  $status = $this->resolver->resolve($availableStock, $requestedQuantity);

  expect($status)->toBe($expectedStatus);
})->with([
  'null stock returns unknown'               => [null, 5, 'unknown'],
  'zero stock returns out_of_stock'          => [0, 1, 'out_of_stock'],
  'stock equal to requested returns available'  => [5, 5, 'available'],
  'stock greater than requested returns available' => [10, 2, 'available'],
  'stock less than requested returns insufficient' => [3, 5, 'insufficient'],
]);
