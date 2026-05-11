<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\ContentAccess\ActivateContentAccessAction;
use App\Domains\Subscription\Actions\ContentAccess\CreateContentAccessAction;
use App\Domains\Subscription\Actions\ContentAccess\DisableContentAccessMetadataAction;
use App\Domains\Subscription\Actions\ContentAccess\ListContentAccessAction;
use App\Domains\Subscription\Actions\ContentAccess\ShowContentAccessAction;
use App\Domains\Subscription\Actions\ContentAccess\UpdateContentAccessMetadataAction;
use App\Domains\Subscription\DTOs\ContentAccess\ActivateContentAccessDTO;
use App\Domains\Subscription\DTOs\ContentAccess\CreateContentAccessDTO;
use App\Domains\Subscription\DTOs\ContentAccess\UpdateContentAccessMetadataDTO;
use App\Models\ContentAccessMetadata;


class ContentAccessManagementService
{
  public function __construct(

    private CreateContentAccessAction $createAction,
    private UpdateContentAccessMetadataAction $updateAction,
    private DisableContentAccessMetadataAction $disableAction,
    private ActivateContentAccessAction $activateAction,
    private ListContentAccessAction $listAction,
    private ShowContentAccessAction $showAction
  ) {}

  public function create(
    CreateContentAccessDTO $dto
  ): ContentAccessMetadata {

    return $this->createAction
      ->execute($dto);
  }

  public function update(
    UpdateContentAccessMetadataDTO $dto
  ): ContentAccessMetadata {

    return $this->updateAction
      ->execute($dto);
  }

  public function disable(
    ContentAccessMetadata $metadata
  ): ContentAccessMetadata {

    return $this->disableAction
      ->execute($metadata);
  }

  public function activate(
    ActivateContentAccessDTO $dto
  ): ContentAccessMetadata {

    return $this->activateAction
      ->execute($dto);
  }

  public function list(
    ?int $projectId = null
  ) {

    return $this->listAction
      ->execute($projectId);
  }

  public function show(
    int $id
  ): ContentAccessMetadata {

    return $this->showAction
      ->execute($id);
  }
}
