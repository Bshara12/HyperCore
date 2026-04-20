<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\Core\Actions\Action;

class HandleRelationsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataEntry.handleRelations';
  }

  public function __construct(
    private DataEntryRelationRepository $relations
  ) {}

  public function execute(int $entryId, int $dataTypeId, int $projectId, ?array $relations): void
  {
    if (!$relations) {
      return;
    }
    $this->run(function () use ($entryId, $dataTypeId, $projectId, $relations) {
      $this->relations->deleteForEntry($entryId);
      $this->relations->insertForEntry(
        $entryId,
        $dataTypeId,
        $projectId,
        $relations
      );
    });
  }
}
