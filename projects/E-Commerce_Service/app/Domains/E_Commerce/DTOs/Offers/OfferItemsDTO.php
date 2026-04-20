<?php

namespace App\Domains\E_Commerce\DTOs\Offers;

use App\Domains\E_Commerce\Requests\InsertOfferItemsRequest;
use App\Domains\E_Commerce\Requests\RemoveOfferItemsRequest;

class OfferItemsDTO
{
  public function __construct(
    public string $collectionSlug,
    public array $items
  ) {}

  public static function fromInsertRequest(string $collectionSlug, InsertOfferItemsRequest $request): self
  {
    return new self(
      collectionSlug: $collectionSlug,
      items: $request->validated()['items']
    );
  }

  public static function fromRemoveRequest(string $collectionSlug, RemoveOfferItemsRequest $request): self
  {
    return new self(
      collectionSlug: $collectionSlug,
      items: $request->validated()['items']
    );
  }
}
