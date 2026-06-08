<?php

use App\Domains\CMS\Actions\DataCollection\CreateDataCollectionAction;
use App\Domains\CMS\Actions\DataCollection\DeactivateCollectionAction;
use App\Domains\CMS\Actions\DataCollection\DeleteDataCollectionAction;
use App\Domains\CMS\Actions\DataCollection\DeleteDataCollectionItemsAction;
use App\Domains\CMS\Actions\DataCollection\GenerateDynamicItemsAction;
use App\Domains\CMS\Actions\DataCollection\InsertCollectionItemsAction;
use App\Domains\CMS\Actions\DataCollection\RemoveCollectionItemsAction;
use App\Domains\CMS\Actions\DataCollection\ReOrderCollectionItemsAction;
use App\Domains\CMS\Actions\DataCollection\UpdateDataCollectionAction;
use App\Domains\CMS\Read\Actions\DataCollection\GetCollectionEntriesAction;
use App\Domains\CMS\Read\Actions\DataCollection\IndexDataCollectionAction;
use App\Domains\CMS\Read\Actions\DataCollection\ShowDataCollectionDetailsAction;
use App\Domains\CMS\Read\Actions\DataCollection\ShowDataCollectionDetailsByIdAction;
use App\Domains\CMS\DTOs\DataCollection\CollectionItemsDTO;
use App\Domains\CMS\DTOs\DataCollection\CreateDataCollectionDTO;
use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO;
use App\Domains\CMS\Services\DataCollectionService;

beforeEach(function () {
  // تجهيز الـ Mocks لكافة الـ Actions
  $this->createAction            = Mockery::mock(CreateDataCollectionAction::class);
  $this->generateAction          = Mockery::mock(GenerateDynamicItemsAction::class);
  $this->updateAction            = Mockery::mock(UpdateDataCollectionAction::class);
  $this->deleteItemsAction       = Mockery::mock(DeleteDataCollectionItemsAction::class);
  $this->deleteAction            = Mockery::mock(DeleteDataCollectionAction::class);
  $this->indexAction             = Mockery::mock(IndexDataCollectionAction::class);
  $this->showDetailsAction       = Mockery::mock(ShowDataCollectionDetailsAction::class);
  $this->insertItemsAction       = Mockery::mock(InsertCollectionItemsAction::class);
  $this->removeItemsAction       = Mockery::mock(RemoveCollectionItemsAction::class);
  $this->reOrderItemsAction      = Mockery::mock(ReOrderCollectionItemsAction::class);
  $this->getEntriesAction        = Mockery::mock(GetCollectionEntriesAction::class);
  $this->showDetailsByIdAction   = Mockery::mock(ShowDataCollectionDetailsByIdAction::class);
  $this->deactivateCollection    = Mockery::mock(DeactivateCollectionAction::class);

  // حقن الـ Mocks داخل الـ Service
  $this->service = new DataCollectionService(
    $this->createAction,
    $this->generateAction,
    $this->updateAction,
    $this->deleteItemsAction,
    $this->deleteAction,
    $this->indexAction,
    $this->showDetailsAction,
    $this->insertItemsAction,
    $this->removeItemsAction,
    $this->reOrderItemsAction,
    $this->getEntriesAction,
    $this->showDetailsByIdAction,
    $this->deactivateCollection
  );
});

afterEach(function () {
  Mockery::close();
});

// ─── اختبار التابع list ───────────────────────────────────────────
test('it fetches list of data collections by project id', function () {
  $this->indexAction->shouldReceive('execute')->once()->with(1)->andReturn(['collection1']);

  expect($this->service->list(1))->toBe(['collection1']);
});

// ─── اختبارات التابع create ───────────────────────────────────────
test('it creates a static data collection without generating items', function () {
  $dto = new CreateDataCollectionDTO(
    project_id: 1,
    data_type_id: 2,
    name: 'Static Coll',
    slug: 'static-coll',
    type: 'static',
    conditions: [],
    conditions_logic: 'and',
    description: null,
    is_active: true,
    is_offer: false,
    settings: []
  );

  $mockedCollection = (object) ['id' => 10, 'type' => 'static'];

  $this->createAction->shouldReceive('execute')->once()->with($dto)->andReturn($mockedCollection);
  // 💡 نؤكد عدم استدعاء الـ generateAction لأن النوع ليس dynamic
  $this->generateAction->shouldNotReceive('execute');

  $result = $this->service->create($dto);
  expect($result)->toBe($mockedCollection);
});

test('it creates a dynamic data collection and generates items', function () {
  $dto = new CreateDataCollectionDTO(
    project_id: 1,
    data_type_id: 2,
    name: 'Dynamic Coll',
    slug: 'dynamic-coll',
    type: 'dynamic',
    conditions: [],
    conditions_logic: 'and',
    description: null,
    is_active: true,
    is_offer: false,
    settings: []
  );

  $mockedCollection = (object) ['id' => 11, 'type' => 'dynamic'];

  $this->createAction->shouldReceive('execute')->once()->with($dto)->andReturn($mockedCollection);
  // 🔥 نتحقق من استدعاء توليد العناصر الديناميكية
  $this->generateAction->shouldReceive('execute')->once()->with($mockedCollection);

  $result = $this->service->create($dto);
  expect($result)->toBe($mockedCollection);
});

// ─── اختبارات التابع update ───────────────────────────────────────
test('it updates a static data collection without resetting items', function () {
  $dto = (object) ['collection_id' => 10, 'name' => 'Updated Static'];
  $mockedCollection = (object) ['id' => 10, 'type' => 'static'];

  $this->updateAction->shouldReceive('execute')->once()->with($dto)->andReturn($mockedCollection);
  $this->deleteItemsAction->shouldNotReceive('execute');
  $this->generateAction->shouldNotReceive('execute');

  $result = $this->service->update($dto);
  expect($result)->toBe($mockedCollection);
});

test('it updates a dynamic data collection, deletes old items and regenerates new ones', function () {
  $dto = (object) ['collection_id' => 11, 'name' => 'Updated Dynamic'];
  $mockedCollection = (object) ['id' => 11, 'type' => 'dynamic'];

  $this->updateAction->shouldReceive('execute')->once()->with($dto)->andReturn($mockedCollection);
  // 🔥 التأكد من تفريغ العناصر القديمة وإعادة توليد الجديدة للـ dynamic
  $this->deleteItemsAction->shouldReceive('execute')->once()->with(11);
  $this->generateAction->shouldReceive('execute')->once()->with($mockedCollection);

  $result = $this->service->update($dto);
  expect($result)->toBe($mockedCollection);
});

// ─── اختبار بقية التوابع المباشرة Proxy Methods ───────────────────
test('it deletes a data collection by slug', function () {
  $this->deleteAction->shouldReceive('execute')->once()->with('some-slug');
  $this->service->delete('some-slug');
});

test('it shows data collection details by keys', function () {
  $this->showDetailsAction->shouldReceive('execute')->once()->with('proj-key', 'coll-slug')->andReturn(['details']);
  expect($this->service->show('proj-key', 'coll-slug'))->toBe(['details']);
});

test('it shows data collection details by id', function () {
  $this->showDetailsByIdAction->shouldReceive('execute')->once()->with(10)->andReturn(['details-by-id']);
  expect($this->service->showById(10))->toBe(['details-by-id']);
});

test('it inserts items to data collection', function () {
  $dto = new CollectionItemsDTO('coll-slug', [1, 2, 3]);
  $this->insertItemsAction->shouldReceive('execute')->once()->with($dto);
  $this->service->addItems($dto);
});

test('it removes items from data collection', function () {
  $dto = new CollectionItemsDTO('coll-slug', [1, 2, 3]);
  $this->removeItemsAction->shouldReceive('execute')->once()->with($dto);
  $this->service->removeItems($dto);
});

test('it reorders items in data collection', function () {
  $dto = new CollectionItemsDTO('coll-slug', [3, 2, 1]);
  $this->reOrderItemsAction->shouldReceive('execute')->once()->with($dto)->andReturn(true);
  expect($this->service->reOrderItems($dto))->toBeTrue();
});

test('it fetches collection entries', function () {
  $this->getEntriesAction->shouldReceive('execute')->once()->with('proj-key', 'coll-slug')->andReturn(['entries']);
  expect($this->service->getEntries('proj-key', 'coll-slug'))->toBe(['entries']);
});

test('it deactivates a collection', function () {
  $dto = new DeactivateCollectionDTO(project_id: 1, slug: 'coll-slug', is_active: false);
  $this->deactivateCollection->shouldReceive('execute')->once()->with($dto);
  $this->service->deactivate($dto);
});
