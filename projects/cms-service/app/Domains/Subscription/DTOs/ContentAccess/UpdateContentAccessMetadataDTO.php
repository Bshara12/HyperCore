<?php

namespace App\Domains\Subscription\DTOs\ContentAccess;

use App\Domains\Subscription\Requests\ContentAccess\UpdateContentAccessMetadataRequest;
use App\Models\ContentAccessMetadata;

class UpdateContentAccessMetadataDTO
{
  public function __construct(

    public readonly ?int $projectId,

    public readonly string $contentType,

    public readonly int $contentId,

    public readonly bool $requiresSubscription,

    public readonly ?string $requiredFeature,

    public readonly bool $isActive,

    public readonly ?array $metadata,

    public readonly ContentAccessMetadata $contentAccessMetadata
  ) {}

  public static function fromRequest(
    UpdateContentAccessMetadataRequest $request,
    ContentAccessMetadata $metadata
  ): self {

    return new self(

      projectId: $request->project_id,

      contentType: $request->content_type,

      contentId: $request->content_id,

      requiresSubscription: $request->requires_subscription,

      requiredFeature: $request->required_feature,

      isActive: $request->boolean(
        'is_active',
        true
      ),

      // metadata: $request->metadata,
      metadata: $request->input('metadata'),
      contentAccessMetadata: $metadata
    );
  }
}
