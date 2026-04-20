<?php

namespace App\Domains\CMS\DTOs\DataCollection;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\DeactivateCollectionRequest;

class DeactivateCollectionDTO
{
  public function __construct(
    public int $project_id,
    public string $slug,
    public bool $is_active,
  ) {}

  public static function fromRequest($collectionSlug, DeactivateCollectionRequest $request): self
  {
    $project_key = app('currentProject')->public_id;

    $id = app(ProjectRepositoryInterface::class)->findByKey($project_key)->id;

    return new self(
      project_id: $id,
      slug: $collectionSlug,
      is_active: $request->is_active ?? false,
    );
  }
}
