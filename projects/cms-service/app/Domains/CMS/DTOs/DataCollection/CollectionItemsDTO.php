<?php

namespace App\Domains\CMS\DTOs\DataCollection;

use App\Domains\CMS\Requests\InsertCollectionItemsRequest;
use App\Domains\CMS\Requests\RemoveCollectionItemsRequest;
use App\Domains\CMS\Requests\ReOrderCollectionItemsRequest;

class CollectionItemsDTO
{
  public function __construct(
    public string $collectionSlug,
    public array $items
  ) {}

  public static function fromInsertRequest(string $collectionSlug, InsertCollectionItemsRequest $request): self
  {
    return new self(
      collectionSlug: $collectionSlug,
      items: $request->validated()['items']
    );
  }

  public static function fromRemoveRequest(string $collectionSlug, RemoveCollectionItemsRequest $request): self
  {
    return new self(
      collectionSlug: $collectionSlug,
      items: $request->validated()['items']
    );
  }

  public static function fromReOrderRequest(string $collectionSlug, ReOrderCollectionItemsRequest $request): self
  {
    return new self(
      collectionSlug: $collectionSlug,
      items: $request->validated()['items']
    );
  }
}
