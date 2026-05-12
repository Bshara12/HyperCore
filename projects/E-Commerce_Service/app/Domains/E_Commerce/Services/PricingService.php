<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Services\CMS\CMSApiClient;

class PricingService
{
  public function __construct(
    private FetchEntriesByIdsAction $fetchEntries,
    private EnrichEntriesWithPricesAction $pricing,
    private CMSApiClient $cms
  ) {}

  // 🔥 للكارت
  // public function calculate(array $entryIds, ?string $code = null): array
  // {
  //   $entries = $this->fetchEntries->execute($entryIds);

  //   return $this->pricing->execute($entries, $code);
  // }
  public function calculate(array $entryIds): array
  {
    $entries = $this->fetchEntries->execute($entryIds);

    return $this->pricing->execute($entries);
  }

  // 🔥 للـ products page
  // public function fromCollection(string $collection, ?string $code = null): array
  // {
  //     $entries = $this->cms->getCollectionBySlug($collection);
  //     return $this->pricing->execute($entries, $code);
  // }
  public function fromCollection(string $collectionSlug, ?string $code = null): array
  {
    $collection = $this->cms->getCollectionBySlug($collectionSlug);

    // 🔥 استخرج entries الصح
    $entries = collect($collection['items'])
      ->pluck('entry')
      ->toArray();

    return $this->pricing->execute($entries);
    // return $this->pricing->execute($entries, $code);
  }

  // test
  public function fromDataType(string $dataTypeSlug, ?string $code = null): array
  {
    $entries = $this->cms->getEntriesByDataType($dataTypeSlug);

    return $this->pricing->execute($entries);
    // return $this->pricing->execute($entries, $code);

  }
}
