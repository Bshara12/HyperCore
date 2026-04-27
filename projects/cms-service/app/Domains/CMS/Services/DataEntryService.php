<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Actions\Data\CreateDataEntryAction;
use App\Domains\CMS\Actions\Data\DeleteDataEntryAction;
use App\Domains\CMS\Actions\data\DeleteValuesAction;
use App\Domains\CMS\Actions\data\HandleRelationsAction;
use App\Domains\CMS\Actions\data\HandleSeoAction;
use App\Domains\CMS\Actions\data\InsertValuesAction;
use App\Domains\CMS\Actions\data\MergeFilesAction;
use App\Domains\CMS\Actions\data\NormalizeScheduledAtAction;
use App\Domains\CMS\Actions\data\ResolveStateAction;
use App\Domains\CMS\Actions\data\ValidateFieldsAction;
use App\Domains\CMS\DTOs\Data\CreateDataEntryDto;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Requests\DataEntryRequest;
use App\Events\DataEntrySavedEvent;
use App\Events\EntryChanged;
use App\Support\CurrentProject;
use DomainException;
use Illuminate\Support\Facades\DB;

class DataEntryService
{
  public function __construct(
    private DataEntryRepositoryInterface $entries,
    private MergeFilesAction $mergeFiles,
    private NormalizeScheduledAtAction $normalizeScheduledAt,
    private ValidateFieldsAction $validateFields,
    private HandleSeoAction $handleSeo,
    private InsertValuesAction $insertValues,
    private DeleteValuesAction $deleteValues,
    private HandleRelationsAction $handleRelations,
    private ResolveStateAction $resolveState,
    private DeleteDataEntryAction $deleteEntry,
    private CreateDataEntryAction $createAction,
    private DataEntryValueRepository $datavalue,

  ) {}


  // public function create(
  //   int $projectId,
  //   int $dataTypeId,
  //   CreateDataEntryDto $dto,
  //   ?int $userId
  // ) {
  //   return $this->createAction->execute(
  //     $projectId,
  //     $dataTypeId,
  //     $dto,
  //     $userId
  //   );
  // }
  public function create(
    int $projectId,
    int $dataTypeId,
    string $slug,
    CreateDataEntryDto $dto,
    ?int $userId
  ) {
    return DB::transaction(function () use ($projectId, $dataTypeId, $slug, $dto, $userId) {
      // dd("sdf");

      $dto->scheduled_at = $this->normalizeScheduledAt
        ->execute($dto->scheduled_at, $dto->status);

      $this->validateFields
        ->execute($dataTypeId, $dto->values);

      $entry = $this->createAction->execute(
        $projectId,
        $dataTypeId,
        $slug,
        $userId
      );
      $this->resolveState
        ->execute($entry, $dto->status, $dto->scheduled_at);

      // dd($dto->values);

      $this->insertValues
        ->execute($entry->id, $dataTypeId, $dto->values);

      $this->handleSeo
        ->execute($entry->id, $dto->seo, $dto->values);

      $this->handleRelations
        ->execute(
          $entry->id,
          $dataTypeId,
          $projectId,
          $dto->relations
        );

      $entry->load('values');

      // event(new EntryChanged($entry, $userId));
      event(new DataEntrySavedEvent($entry));
      return $entry;
    });
  }




  public function update(DataEntryRequest $request, CreateDataEntryDto $dto, ?int $userId)
  {
    return DB::transaction(function () use ($request, $dto, $userId) {
      $entryId = $request->entryId();


      $projectId = $request->projectId();

      // $dataTypeId = $request->dataTypeId();



      $entry = $this->entries->findForProjectOrFail($entryId, $projectId);
      $dataTypeId = $entry->data_type_id;

      $dto->values = $this->mergeFiles->execute($dto->values, $request->filesInput(), $projectId, $dataTypeId);


      $dto->scheduled_at = $this->normalizeScheduledAt->execute($dto->scheduled_at, $dto->status);

      $enforceRequired = !$request->isMethod('patch');
      $this->validateFields->execute($dataTypeId, $dto->values, $enforceRequired);

      if ($request->filled('status')) {
        $this->resolveState->execute($entry, $dto->status, $dto->scheduled_at);
      }

      if ($request->isMethod('patch')) {
        if (!empty($dto->values)) {
          $this->datavalue->replacePartial($entryId, $dataTypeId, $dto->values);
        }
      } else {
        $this->deleteValues->execute($entryId);
        $this->insertValues->execute($entryId, $dataTypeId, $dto->values);
      }

      if ($request->filled('seo')) {
        $this->handleSeo->execute($entryId, $dto->seo, $dto->values);
      }

      if ($request->filled('relations')) {
        $this->handleRelations->execute($entryId, $dataTypeId, $projectId, $dto->relations);
      }

      $entry->load('values');

      event(new EntryChanged($entry, $userId));
      event(new DataEntrySavedEvent($entry));

      return $entry;
    });
  }

  public function destroy(int $entryId, int $projectId)
  {
    $this->deleteEntry->execute($entryId, $projectId);
  }
}
