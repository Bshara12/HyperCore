<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Models\ContentAccessMetadata;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;

class EloquentContentAccessMetadataRepository
implements ContentAccessMetadataRepositoryInterface
{
  public function findContentRule(
    string $contentType,
    int $contentId
  ): ?ContentAccessMetadata {

    return ContentAccessMetadata::query()

      ->where('content_type', $contentType)

      ->where('content_id', $contentId)

      ->first();
  }
  public function create(
    array $data
  ): ContentAccessMetadata {

    return ContentAccessMetadata::create(
      $data
    );
  }
  public function update(
    ContentAccessMetadata $metadata,
    array $data
  ): ContentAccessMetadata {

    $metadata->update($data);

    return $metadata->fresh();
  }
  public function disable(
    ContentAccessMetadata $metadata
  ): ContentAccessMetadata {

    $metadata->update([
      'is_active' => false
    ]);

    return $metadata->fresh();
  }
  public function paginate(
    ?int $projectId = null
  ) {

    return ContentAccessMetadata::query()

      ->when(

        $projectId,

        fn($query) => $query
          ->where(
            'project_id',
            $projectId
          )
      )

      ->latest()

      ->paginate(20);
  }

  public function findById(
    int $id
  ): ?ContentAccessMetadata {

    return ContentAccessMetadata::query()
      ->find($id);
  }

  public function findManyRules(
    string $contentType,
    array $contentIds
  ) {

    return ContentAccessMetadata::query()

      ->where('content_type', $contentType)

      ->whereIn('content_id', $contentIds)

      ->where('is_active', true)

      ->get()

      ->keyBy('content_id');
  }
}
