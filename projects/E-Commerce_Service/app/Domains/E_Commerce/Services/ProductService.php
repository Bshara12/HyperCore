<?php

namespace App\Domains\E_Commerce\Services;


class ProductService
{
  public function __construct(
    private PricingService $pricing
  ) {}
  public function getProducts(string $dataTypeSlug, ?string $code = null)
  {
    return $this->pricing->fromDataType($dataTypeSlug, $code);
  }
}
