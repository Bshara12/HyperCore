<?php

namespace App\Domains\CMS\Actions\Data;

use App\Domains\CMS\DTOs\Data\CreateDataEntryDto;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\CMS\Repositories\Interface\SeoEntryRepository;
use App\Domains\CMS\Services\SeoGeneratorService;
use App\Domains\CMS\States\DataEntryStateResolver;
use App\Events\EntryChanged;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\DB;

// class CreateDataEntryAction
// {
//     public function __construct(
//         private DataEntryRepositoryInterface $entries,
//         private DataEntryValueRepository $values,
//         private SeoEntryRepository $seo,
//         private SeoGeneratorService $seoGenerator,
//         private DataEntryStateResolver $stateResolver,
//         private DataEntryRelationRepository $relations,
//         private ValidateFieldsAction $validateFields
//     ) {}

//     public function execute(
//         int $projectId,
//         int $dataTypeId,
//         CreateDataEntryDto $dto,
//         ?int $userId = null
//     ) {
//         return DB::transaction(function () use ($projectId, $dataTypeId, $dto, $userId) {

//             // 1️⃣ Validate
//             $this->validateFields->execute($dataTypeId, $dto->values);

//             // 2️⃣ Create Entry
//             $entry = $this->entries->create([
//                 'project_id'   => $projectId,
//                 'data_type_id' => $dataTypeId,
//                 'status'       => 'draft',
//                 'scheduled_at' => $dto->status === 'scheduled'
//                     ? $dto->scheduled_at
//                     : null,
//                 'created_by'   => $userId,
//             ]);

//             // 3️⃣ Resolve State
//             $state = $this->stateResolver->resolve($entry);

//             if ($dto->status === 'published') {
//                 $state->publish($entry);
//             }

//             if ($dto->status === 'scheduled') {
//                 $state->schedule($entry, $dto->scheduled_at);
//             }

//             // 4️⃣ Insert Values
//             $this->values->bulkInsert(
//                 $entry->id,
//                 $dataTypeId,
//                 $dto->values
//             );

//             // 5️⃣ SEO
//             if ($dto->seo) {
//                 $this->seo->insertForEntry($entry->id, $dto->seo);
//             } else {
//                 $generatedSeo = $this->seoGenerator->generate($dto->values);
//                 $this->seo->insertForEntry($entry->id, $generatedSeo);
//             }

//             // 6️⃣ Relations
//             if ($dto->relations) {
//                 $this->relations->insertForEntry(
//                     $entry->id,
//                     $dataTypeId,
//                     $projectId,
//                     $dto->relations
//                 );
//             }

//             $entry->load('values');

//             event(new EntryChanged($entry, $userId));

//             return $entry;
//         });
//     }
// }

class CreateDataEntryAction
{
  public function __construct(
    private DataEntryRepositoryInterface $entries
  ) {}

  public function execute(
    int $projectId,
    int $dataTypeId,
    string $slug,
    ?int $userId
  ) {

 event(new SystemLogEvent(
     module: 'cms',
     eventType: 'create_data',
     userId: $userId,
     entityType: 'data',
     entityId:null
 ));

    return $this->entries->create([
      'project_id' => $projectId,
      'data_type_id' => $dataTypeId,
      'slug' => $slug,
      'status' => 'draft',
      'created_by' => $userId
    ]);
  }
}
