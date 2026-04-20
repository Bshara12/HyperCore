<?php

namespace App\Domains\CMS\Repositories\Interface;

interface SeoEntryRepository
{
  public function insertForEntry(
    int $entryId,
    array $seoData
  ): void;
  public function getForEntry(int $entryId): ?array;
  public function deleteForEntry(int $entryId): void;
}
