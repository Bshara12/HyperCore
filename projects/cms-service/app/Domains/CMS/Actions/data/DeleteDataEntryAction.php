<?php

namespace App\Domains\CMS\Actions\Data;

use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\CMS\Repositories\Interface\SeoEntryRepository;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteDataEntryAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataEntry.delete';
  }

  public function __construct(
    private DataEntryRepositoryInterface $entries,
    private DataEntryValueRepository $values,
    private DataEntryRelationRepository $relations,
    private SeoEntryRepository $seo,
    private DeleteEntryFilesAction $deleteFiles
  ) {}

  // public function execute(int $entryId, int $projectId): void
  // {
  //   $this->run(function () use ($entryId, $projectId) {

  //     $entry = $this->entries->findForProjectOrFail($entryId, $projectId);

  //     $children = $this->relations->getEntriesWhereRelatedIs($entryId);

  //     foreach ($children as $child) {
  //       $this->execute($child['data_entry_id'], $projectId);
  //     }


  //     $this->deleteFiles->execute($entryId);

  //     $this->values->deleteForEntry($entryId);

  //     $this->relations->deleteForEntry($entryId);
  //     $this->relations->deleteWhereRelatedIs($entryId);

  //     $this->seo->deleteForEntry($entryId);

  //     $entry->forceDelete();
  //   });
  // }

  public function execute(int $entryId, int $projectId): void
  {
    $this->run(function () use ($entryId, $projectId) {

      $paths = [];

      DB::transaction(function () use ($entryId, $projectId, &$paths) {

        $entry = $this->entries->findForProjectOrFail($entryId, $projectId);

        $children = $this->relations->getEntriesWhereRelatedIs($entryId);

        foreach ($children as $child) {
          $this->execute($child['data_entry_id'], $projectId);
        }

        $paths = $this->deleteFiles->execute($entryId);

        $this->values->deleteForEntry($entryId);

        $this->relations->deleteForEntry($entryId);
        $this->relations->deleteWhereRelatedIs($entryId);

        $this->seo->deleteForEntry($entryId);

        $entry->forceDelete();
      });

      if (!empty($paths)) {
        Storage::disk('supabase')->delete($paths);
      }
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'delete_data',
        userId: null,
        entityType: 'data',
        entityId: $entryId
      ));
    });
  }
}
