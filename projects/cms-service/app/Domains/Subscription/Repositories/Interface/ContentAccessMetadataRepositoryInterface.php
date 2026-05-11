<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Models\ContentAccessMetadata;

interface ContentAccessMetadataRepositoryInterface
{
  public function findContentRule(
    string $contentType,
    int $contentId
  ): ?ContentAccessMetadata;
  public function create(
    array $data
  ): ContentAccessMetadata;

  public function update(
    ContentAccessMetadata $metadata,
    array $data
  ): ContentAccessMetadata;

  public function disable(
    ContentAccessMetadata $metadata
  ): ContentAccessMetadata;
  public function paginate(
    ?int $projectId = null
  );

  public function findById(
    int $id
  ): ?ContentAccessMetadata;
}
