<?php

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
use App\Domains\CMS\Services\DataEntryService;
use App\Events\DataEntrySavedEvent;
use App\Events\EntryChanged;
use App\Models\DataType;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
  Event::fake(); // 💡 عزل ومنع إطلاق الأحداث الحقيقية ومراقبتها تلقائياً

  // إعداد الـ Mocks لجميع الـ Dependencies الـ 12
  $this->entries              = Mockery::mock(DataEntryRepositoryInterface::class);
  $this->mergeFiles           = Mockery::mock(MergeFilesAction::class);
  $this->normalizeScheduledAt = Mockery::mock(NormalizeScheduledAtAction::class);
  $this->validateFields       = Mockery::mock(ValidateFieldsAction::class);
  $this->handleSeo            = Mockery::mock(HandleSeoAction::class);
  $this->insertValues         = Mockery::mock(InsertValuesAction::class);
  $this->deleteValues         = Mockery::mock(DeleteValuesAction::class);
  $this->handleRelations      = Mockery::mock(HandleRelationsAction::class);
  $this->resolveState         = Mockery::mock(ResolveStateAction::class);
  $this->deleteEntry          = Mockery::mock(DeleteDataEntryAction::class);
  $this->createAction         = Mockery::mock(CreateDataEntryAction::class);
  $this->datavalue            = Mockery::mock(DataEntryValueRepository::class);

  $this->service = new DataEntryService(
    $this->entries,
    $this->mergeFiles,
    $this->normalizeScheduledAt,
    $this->validateFields,
    $this->handleSeo,
    $this->insertValues,
    $this->deleteValues,
    $this->handleRelations,
    $this->resolveState,
    $this->deleteEntry,
    $this->createAction,
    $this->datavalue
  );
});

afterEach(function () {
  Mockery::close();
});

// ─── اختبار التابع Create ───────────────────────────────────────────

test('it creates a data entry successfully and dispatches events', function () {
  $dataType = (new DataType())->forceFill(['id' => 5]);
  $dto = new CreateDataEntryDTO(
    values: ['title' => 'Test Entry'],
    seo: ['meta_title' => 'SEO Title'],
    relations: [['relation_id' => 1, 'related_entry_ids' => [2]]],
    status: 'published',
    scheduled_at: null
  );

  // تجهيز موديل الـ Entry الوهمي متضمناً ميثود الـ load للأشياء التابعة
  $mockedEntry = Mockery::mock(\App\Models\DataEntry::class)->makePartial();
  $mockedEntry->id = 50;
  $mockedEntry->shouldReceive('load')->once()->with('values')->andReturnSelf();

  // إعداد توقعات الـ Actions بالتسلسل
  $this->normalizeScheduledAt->shouldReceive('execute')->once()->with(null, 'published')->andReturn(null);
  $this->validateFields->shouldReceive('execute')->once()->with(5, $dto->values);
  $this->createAction->shouldReceive('execute')->once()->with(1, $dataType, 'test-slug', null)->andReturn($mockedEntry);
  $this->resolveState->shouldReceive('execute')->once()->with($mockedEntry, 'published', null);
  $this->insertValues->shouldReceive('execute')->once()->with(50, 5, $dto->values);
  $this->handleSeo->shouldReceive('execute')->once()->with(50, $dto->seo, $dto->values);
  $this->handleRelations->shouldReceive('execute')->once()->with(50, 5, 1, $dto->relations);

  $result = $this->service->create(1, $dataType, 'test-slug', $dto, null);

  expect($result)->toBe($mockedEntry);
  Event::assertDispatched(DataEntrySavedEvent::class);
});

// ─── اختبارات التابع Update ───────────────────────────────────────────

test('it updates a data entry via PUT method completely', function () {
  $request = Mockery::mock(DataEntryRequest::class);
  $request->shouldReceive('entryId')->once()->andReturn(50);
  $request->shouldReceive('projectId')->once()->andReturn(1);
  $request->shouldReceive('filesInput')->once()->andReturn([]);
  $request->shouldReceive('isMethod')->with('patch')->andReturn(false);
  $request->shouldReceive('filled')->with('status')->andReturn(true);
  $request->shouldReceive('filled')->with('seo')->andReturn(true);
  $request->shouldReceive('filled')->with('relations')->andReturn(true);

  $dto = new CreateDataEntryDTO(
    values: ['title' => 'Updated Complete'],
    seo: ['meta' => 'data'],
    relations: [[1]],
    status: 'scheduled',
    scheduled_at: '2026-06-01'
  );

  $mockedEntry = Mockery::mock(\App\Models\DataEntry::class)->makePartial();
  $mockedEntry->id = 50;
  $mockedEntry->data_type_id = 5;
  $mockedEntry->shouldReceive('load')->once()->with('values')->andReturnSelf();

  $this->entries->shouldReceive('findForProjectOrFail')->once()->with(50, 1)->andReturn($mockedEntry);
  $this->mergeFiles->shouldReceive('execute')->once()->with($dto->values, [], 1, 5)->andReturn($dto->values);
  $this->normalizeScheduledAt->shouldReceive('execute')->once()->with('2026-06-01', 'scheduled')->andReturn('2026-06-01');
  $this->validateFields->shouldReceive('execute')->once()->with(5, $dto->values, true);
  $this->resolveState->shouldReceive('execute')->once()->with($mockedEntry, 'scheduled', '2026-06-01');

  // سيعبر من مسار المسح ثم الإدخال من جديد بسبب استخدام PUT وليس PATCH
  $this->deleteValues->shouldReceive('execute')->once()->with(50);
  $this->insertValues->shouldReceive('execute')->once()->with(50, 5, $dto->values);

  $this->handleSeo->shouldReceive('execute')->once()->with(50, $dto->seo, $dto->values);
  $this->handleRelations->shouldReceive('execute')->once()->with(50, 5, 1, $dto->relations);

  $result = $this->service->update($request, $dto, 99);

  expect($result)->toBe($mockedEntry);
  Event::assertDispatched(EntryChanged::class);
  Event::assertDispatched(DataEntrySavedEvent::class);
});

test('it updates a data entry via PATCH method partially', function () {
  $request = Mockery::mock(DataEntryRequest::class);
  $request->shouldReceive('entryId')->once()->andReturn(50);
  $request->shouldReceive('projectId')->once()->andReturn(1);
  $request->shouldReceive('filesInput')->once()->andReturn([]);
  $request->shouldReceive('isMethod')->with('patch')->andReturn(true);
  $request->shouldReceive('filled')->with('status')->andReturn(false);
  $request->shouldReceive('filled')->with('seo')->andReturn(false);
  $request->shouldReceive('filled')->with('relations')->andReturn(false);

  $dto = new CreateDataEntryDTO(values: ['partial_field' => 'value'], status: 'draft');

  $mockedEntry = Mockery::mock(\App\Models\DataEntry::class)->makePartial();
  $mockedEntry->id = 50;
  $mockedEntry->data_type_id = 5;
  $mockedEntry->shouldReceive('load')->once()->with('values')->andReturnSelf();

  $this->entries->shouldReceive('findForProjectOrFail')->once()->with(50, 1)->andReturn($mockedEntry);
  $this->mergeFiles->shouldReceive('execute')->once()->with($dto->values, [], 1, 5)->andReturn($dto->values);
  $this->normalizeScheduledAt->shouldReceive('execute')->once()->with(null, 'draft')->andReturn(null);
  $this->validateFields->shouldReceive('execute')->once()->with(5, $dto->values, false);

  // سيمر بمسار الاستبدال الجزئي المميز للـ PATCH فقط
  $this->datavalue->shouldReceive('replacePartial')->once()->with(50, 5, $dto->values);
  $this->deleteValues->shouldNotReceive('execute');

  $result = $this->service->update($request, $dto, 99);

  expect($result)->toBe($mockedEntry);
});

// ─── اختبار التابع Destroy ─────────────────────────────────────────

test('it triggers data entry destruction action', function () {
  $this->deleteEntry->shouldReceive('execute')->once()->with(50, 1);

  $this->service->destroy(50, 1);
});
