<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\Core\Actions\Action;

class DeleteValuesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataEntry.deleteValues';
  }
  public function __construct(
    private DataEntryValueRepository $values
  ) {}

  public function execute(int $entryId): void
  {
    $this->run(function () use ($entryId) {
      $this->values->deleteForEntry($entryId);
    });
  }
}
