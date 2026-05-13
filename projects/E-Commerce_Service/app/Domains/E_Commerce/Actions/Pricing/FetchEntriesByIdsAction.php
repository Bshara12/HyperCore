<?php

namespace App\Domains\E_Commerce\Actions\Pricing;

use App\Services\CMS\CMSApiClient;

class FetchEntriesByIdsAction
{
  public function __construct(
    private CMSApiClient $cms
  ) {}

  public function execute(array $entryIds): array
  {
    // ❗ هون الفكرة: ما تعتمد على entries endpoint القديم

    return $this->cms->getEntriesByIds($entryIds);
  }
}
