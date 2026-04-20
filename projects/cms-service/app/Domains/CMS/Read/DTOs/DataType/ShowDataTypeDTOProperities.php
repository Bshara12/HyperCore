<?php

namespace App\Domains\CMS\Read\DTOs\DataType;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;

class ShowDataTypeDTOProperities
{
  public function __construct(
    public int $project_id,
    public string $slug,
  ) {}

  public static function fromRequest(string $slug): self
  {
    $project_key = app('currentProject')->public_id;

    $id = app(ProjectRepositoryInterface::class)->findByKey($project_key)->id;

    return new self(
      project_id: $id,
      slug: $slug,
    );
  }
}
