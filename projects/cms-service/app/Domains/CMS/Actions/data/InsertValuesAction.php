<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class InsertValuesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataEntry.insertValues';
  }

  public function __construct(
    private DataEntryValueRepository $values
  ) {}

  public function execute(int $entryId, int $dataTypeId, array $values): void
  {
    $this->run(function () use ($entryId, $dataTypeId, $values) {
      $this->values->bulkInsert($entryId, $dataTypeId, $values);
    });
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'create_value',
      userId:null,
      entityType: 'data',
      entityId: $entryId
    ));
  }
}
