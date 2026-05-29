<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\Subscription\Services\DomainEventService;
use App\Events\SystemLogEvent;
use App\Models\DataType;

class CreateDataEntryAction
{
  public function __construct(
    private DataEntryRepositoryInterface $entries,
    private DomainEventService $domainEventService

  ) {}

  public function execute(
    int $projectId,
    DataType $dataType,
    string $slug,
    ?int $userId
  ) {

    /*
            |--------------------------------------------------------------------------
            | Subscription Usage Engine
            |--------------------------------------------------------------------------
            */

    $this->domainEventService
      ->dispatch(

        userId: $userId,

        projectId: $projectId,

        eventKey: sprintf(
          '%s.create',
          $dataType->slug
        )
      );

    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'create_data',
      userId: $userId,
      entityType: 'data',
      entityId: null
    ));

    return $this->entries->create([
      'project_id' => $projectId,
      'data_type_id' => $dataType->id,
      'slug' => $slug,
      'status' => 'draft',
      'created_by' => $userId,
    ]);
  }
}
