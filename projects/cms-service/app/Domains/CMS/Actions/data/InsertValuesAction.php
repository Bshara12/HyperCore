<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\Core\Actions\Action;

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
  }
}
