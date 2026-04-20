<?php

namespace App\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\GetEntriesByDataTypeSlugAction;
use App\Domains\CMS\Read\Actions\GetEntriesBySameTypeAction;
use App\Domains\CMS\Read\Actions\GetEntryDetailAction;
use App\Domains\CMS\Read\Actions\GetEntryWithRelationsAction;
use App\Domains\CMS\Read\Actions\GetProjectEntriesAction;
use App\Domains\CMS\Read\Actions\GetProjectEntriesTreeAction;
use App\Models\DataEntry;

class EntryReadService
{
  public function __construct(
    private GetEntryDetailAction $getEntryDetailAction,
    private GetEntryWithRelationsAction $getEntryWithRelationsAction,
    private GetEntriesBySameTypeAction $getEntriesBySameTypeAction,
    private GetProjectEntriesAction $getProjectEntriesAction,
    private GetProjectEntriesTreeAction $getProjectEntriesTreeAction,
    private GetEntriesByDataTypeSlugAction $getEntriesByDataTypeSlugAction

  ) {}

  public function getDetail(int $entryId, ?string $lang)
  {
    return $this->getEntryDetailAction->execute($entryId, $lang);
  }
  public function getWithRelations(int $entryId, ?string $lang)
  {
    return $this->getEntryWithRelationsAction->execute($entryId, $lang);
  }
  // public function getSameType(int $entryId, ?string $lang)
  // {
  //   return $this->getEntriesBySameTypeAction->execute($entryId, $lang);
  // }
  public function getSameType(
    int $entryId,
    ?string $lang,
    int $page = 1,
    int $perPage = 20,
    bool $all = false
  ) {
    return $this->getEntriesBySameTypeAction
      ->execute($entryId, $lang, $page, $perPage, $all);
  }
  public function getSameTypeFiltered(
    int $entryId,
    ?string $lang,
    ?string $dateFrom,
    ?string $dateTo,
    ?int $fieldId,
    ?string $search,
    bool $all = false,
    int $page = 1,
    int $perPage = 20
  ) {
    return $this->getEntriesBySameTypeAction->execute(
      $entryId,
      $lang,
      $page,
      $perPage,
      $all,
      $dateFrom,
      $dateTo,
      $fieldId,
      $search
    );
  }
  public function getProjectEntries(int $projectId, array $filters)
  {
    return $this->getProjectEntriesAction->execute($projectId, $filters);
  }
  public function getProjectEntriesTree(int $projectId, array $filters)
  {
    return $this->getProjectEntriesTreeAction->execute($projectId, $filters);
  }
  public function getEntriesByDataTypeSlug(int $projectId, string $slug, array $filters)
  {
    return $this->getEntriesByDataTypeSlugAction
      ->execute($projectId, $slug, $filters);
  }
  public function showMany($ids)
  {
    return DataEntry::with(['values','values.field'])
      ->whereIn('id', $ids)
      ->get();
  }
}
