<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Actions\data\CreateDataEntryAction;
use App\Domains\CMS\Actions\data\DeleteDataEntryAction;
use App\Domains\CMS\Actions\data\DeleteValuesAction;
use App\Domains\CMS\Actions\data\HandleRelationsAction;
use App\Domains\CMS\Actions\data\HandleSeoAction;
use App\Domains\CMS\Actions\data\InsertValuesAction;
use App\Domains\CMS\Actions\data\MergeFilesAction;
use App\Domains\CMS\Actions\data\NormalizeScheduledAtAction;
use App\Domains\CMS\Actions\data\ResolveStateAction;
use App\Domains\CMS\Actions\data\ValidateFieldsAction;
use App\Domains\CMS\DTOs\Data\CreateDataEntryDTO;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Requests\DataEntryRequest;
use App\Events\DataEntrySavedEvent;
use App\Events\EntryChanged;
use App\Events\EntryRemovedFromSearch;
use App\Models\DataType;
use Illuminate\Support\Facades\DB;

class DataEntryService
{
    public function __construct(
        private DataEntryRepositoryInterface $entries,
        private MergeFilesAction             $mergeFiles,
        private NormalizeScheduledAtAction   $normalizeScheduledAt,
        private ValidateFieldsAction         $validateFields,
        private HandleSeoAction              $handleSeo,
        private InsertValuesAction           $insertValues,
        private DeleteValuesAction           $deleteValues,
        private HandleRelationsAction        $handleRelations,
        private ResolveStateAction           $resolveState,
        private DeleteDataEntryAction        $deleteEntry,
        private CreateDataEntryAction        $createAction,
        private DataEntryValueRepository     $datavalue,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────

    public function create(
        int              $projectId,
        DataType         $dataType,
        string           $slug,
        CreateDataEntryDTO $dto,
        ?int             $userId
    ) {
        return DB::transaction(function () use ($projectId, $dataType, $slug, $dto, $userId) {
            $dataTypeId = $dataType->id;

            $dto->scheduled_at = $this->normalizeScheduledAt
                ->execute($dto->scheduled_at, $dto->status);

            $this->validateFields
                ->execute($dataTypeId, $dto->values);

            $entry = $this->createAction->execute(
                $projectId,
                $dataType,
                $slug,
                $userId
            );

            $this->resolveState
                ->execute($entry, $dto->status, $dto->scheduled_at);

            $this->insertValues
                ->execute($entry->id, $dataTypeId, $dto->values);

            $this->handleSeo
                ->execute($entry->id, $dto->seo, $dto->values);

            $this->handleRelations
                ->execute($entry->id, $dataTypeId, $projectId, $dto->relations);

            $entry->load('values');

            event(new DataEntrySavedEvent($entry));

            return $entry;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────

    public function update(DataEntryRequest $request, CreateDataEntryDTO $dto, ?int $userId)
    {
        return DB::transaction(function () use ($request, $dto, $userId) {
            $entryId   = $request->entryId();
            $projectId = $request->projectId();

            $entry      = $this->entries->findForProjectOrFail($entryId, $projectId);
            $dataTypeId = $entry->data_type_id;

            // ─── هل كانت published قبل التعديل؟ ─────────────────────────
            // نحتاج هذا لنعرف إذا كانت ستُحذف من الفهرس
            $wasPublished = $entry->status === 'published';

            $dto->values = $this->mergeFiles->execute(
                $dto->values, $request->filesInput(), $projectId, $dataTypeId
            );

            $dto->scheduled_at = $this->normalizeScheduledAt
                ->execute($dto->scheduled_at, $dto->status);

            $enforceRequired = ! $request->isMethod('patch');
            $this->validateFields->execute($dataTypeId, $dto->values, $enforceRequired);

            if ($request->filled('status')) {
                $this->resolveState->execute($entry, $dto->status, $dto->scheduled_at);
            }

            if ($request->isMethod('patch')) {
                if (! empty($dto->values)) {
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

            // ─── إطلاق Events ─────────────────────────────────────────────
            event(new EntryChanged($entry, $userId));
            event(new DataEntrySavedEvent($entry));

            // ─── إذا كانت published وأصبحت draft/scheduled → أزلها من الفهرس
            // IndexDataEntryListener يُعالج published و archived
            // لكن draft/scheduled لا تُعالَج → نُطلق EntryRemovedFromSearch


            // $newStatus = $entry->fresh()->status;
            $newStatus = $entry->status;

            if ($wasPublished && in_array($newStatus, ['draft', 'scheduled'], true)) {
                event(new EntryRemovedFromSearch(
                    entryId: $entryId,
                    reason: 'unpublished',
                ));
            }

            return $entry;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(int $entryId, int $projectId)
    {
        // نجلب الـ entry قبل الحذف لنعرف هل كانت في الفهرس
        $entry = $this->entries->findForProjectOrFail($entryId, $projectId);
        $wasIndexed = in_array($entry->status, ['published', 'archived'], true);

        $this->deleteEntry->execute($entryId, $projectId);

        // ─── إزالة من search_indices بعد الحذف الناجح ────────────────
        // نُطلق الـ event حتى لو لم تكن مفهرسة (الـ Listener يتعامل مع missing rows)
        if ($wasIndexed) {
            event(new EntryRemovedFromSearch(
                entryId: $entryId,
                reason: 'deleted',
            ));
        }
    }
}