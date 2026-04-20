<?php

namespace   App\Domains\CMS\Actions\Data;

use App\Domains\CMS\Services\DataEntryService;

class CloneDataEntryAction
{
  public function __construct(
    private DataEntryService $service
  ) {}

  public function execute(int $entryId, ?int $userId)
  {
    return $this->service->cloneToDraft($entryId, $userId);
  }
}
