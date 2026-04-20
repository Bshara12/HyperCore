<?php

namespace App\Domains\CMS\DTOs\DataCollection;

use App\Domains\CMS\Requests\UpdateDataCollectionRequest;
use App\Models\DataCollection;
use Illuminate\Support\Str;

class UpdateDataCollectionDTO
{
  public function __construct(
    public int $collection_id,
    public array $data
  ) {}

  public static function fromRequest(UpdateDataCollectionRequest $request, string $collectionSlug): self
  {
    $collection = DataCollection::where('slug', $collectionSlug)->firstOrFail();

    $data = $request->only([
      'name',
      'conditions',
      'conditions_logic',
      'description',
      'settings',
      'is_active',
    ]);

    if ($request->has('name')) {
      $data['slug'] = Str::slug($request->name);
    }

    return new self(
      collection_id: $collection->id,
      data: $data,
    );
  }

  public function toArray(): array
  {
    return $this->data;
  }
}
