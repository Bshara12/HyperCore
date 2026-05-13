<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\OfferService;
use App\Domains\E_Commerce\Actions\Offers\{
  ActivateOfferAction,
  CalculatePricesAction,
  CreateOfferAction,
  DeactivateOfferAction,
  DeleteOfferAction,
  DeleteOfferPricesAction,
  EnterOfferItemsAction,
  InsertOfferItemsAction,
  ProcessScheduledOffersAction,
  ReEvaluateEntryPricesAction,
  RemoveOfferItemsAction,
  SubscribeAction,
  UpdateCollectionAction,
  UpdateOfferAction
};
use App\Domains\E_Commerce\Read\Actions\Offers\{IndexOffersAction, ShowOfferDetailsAction};
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\DTOs\Offers\{
  ActivationOfferDTO,
  CreateOfferDTO,
  OfferItemsDTO,
  SubscribeDTO,
  UpdateOfferDTO
};
use App\Services\CMS\CMSApiClient;
use Mockery;

beforeEach(function () {
  // إنشاء Mocks لكل الـ 18 Dependency
  $this->cms = Mockery::mock(CMSApiClient::class);
  $this->createAction = Mockery::mock(CreateOfferAction::class);
  $this->calculateAction = Mockery::mock(CalculatePricesAction::class);
  $this->enterItemsAction = Mockery::mock(EnterOfferItemsAction::class);
  $this->updateCollection = Mockery::mock(UpdateCollectionAction::class);
  $this->updateOffer = Mockery::mock(UpdateOfferAction::class);
  $this->repository = Mockery::mock(OfferRepositoryInterface::class);
  $this->deleteOfferPricesAction = Mockery::mock(DeleteOfferPricesAction::class);
  $this->reEvaluateAction = Mockery::mock(ReEvaluateEntryPricesAction::class);
  $this->showDetailsAction = Mockery::mock(ShowOfferDetailsAction::class);
  $this->indexAction = Mockery::mock(IndexOffersAction::class);
  $this->deleteOffer = Mockery::mock(DeleteOfferAction::class);
  $this->insertItemsAction = Mockery::mock(InsertOfferItemsAction::class);
  $this->removeItemsAction = Mockery::mock(RemoveOfferItemsAction::class);
  $this->deactivateOffer = Mockery::mock(DeactivateOfferAction::class);
  $this->activateOffer = Mockery::mock(ActivateOfferAction::class);
  $this->action = Mockery::mock(ProcessScheduledOffersAction::class);
  $this->subscribeAction = Mockery::mock(SubscribeAction::class);

  $this->service = new OfferService(
    $this->cms,
    $this->createAction,
    $this->calculateAction,
    $this->enterItemsAction,
    $this->updateCollection,
    $this->updateOffer,
    $this->repository,
    $this->deleteOfferPricesAction,
    $this->reEvaluateAction,
    $this->showDetailsAction,
    $this->indexAction,
    $this->deleteOffer,
    $this->insertItemsAction,
    $this->removeItemsAction,
    $this->deactivateOffer,
    $this->activateOffer,
    $this->action,
    $this->subscribeAction
  );
});

// --- اختبار دالة Create ---

it('creates a dynamic offer and calculates prices', function () {
  $dto = new CreateOfferDTO(1, 1, 'Summer Sale', 'summer-sale', 'dynamic', [], 'and', '', [], false, 0, 'percentage', [], null, null, true);

  $this->createAction->shouldReceive('execute')->once()->with($dto)->andReturn(['id' => 1]);
  $this->calculateAction->shouldReceive('execute')->once()->with(['id' => 1]);

  $this->service->create($dto);
});

it('creates a static offer without calculating prices', function () {
  $dto = new CreateOfferDTO(1, 1, 'Summer Sale', 'summer-sale', 'static', [], 'and', '', [], false, 0, 'percentage', [], null, null, true);

  $this->createAction->shouldReceive('execute')->once()->with($dto)->andReturn(['id' => 1]);
  $this->calculateAction->shouldNotReceive('execute');

  $this->service->create($dto);
});

// --- اختبار دالة Update (المسار المعقد) ---

it('updates offer and recalculates if conditions changed', function () {
  $dto = new UpdateOfferDTO('slug', ['conditions' => [1]], ['benefit_type' => 'percentage']);

  $collection = ['id' => 10, 'type' => 'dynamic'];
  $offer = ['id' => 20, 'benefit_type' => 'percentage'];

  $this->updateCollection->shouldReceive('execute')->once()->andReturn(['data' => $collection]);
  $this->updateOffer->shouldReceive('execute')->once()->andReturn($offer);

  $this->deleteOfferPricesAction->shouldReceive('execute')->once()->with(20);
  $this->calculateAction->shouldReceive('execute')->once()->andReturn(['entry1']);
  $this->reEvaluateAction->shouldReceive('execute')->once()->with(['entry1']);

  $result = $this->service->update($dto);
  expect($result)->toHaveKeys(['collection', 'offer']);
});

// --- اختبار إضافة وحذف العناصر ---

it('adds items and triggers recalculation for dynamic offers', function () {
  $dto = new OfferItemsDTO('slug', [1, 2]);
  $responseData = [
    'message' => 'Items added successfully',
    'collection' => ['type' => 'dynamic'],
    'offer' => ['benefit_type' => 'percentage']
  ];

  $this->insertItemsAction->shouldReceive('execute')->once()->andReturn($responseData);
  $this->calculateAction->shouldReceive('execute')->once()->andReturn(['entry1']);
  $this->reEvaluateAction->shouldReceive('execute')->once();

  $this->service->addItems($dto);
});

it('removes items and re-evaluates prices', function () {
  $dto = new OfferItemsDTO('slug', [1, 2]);

  $this->removeItemsAction->shouldReceive('execute')->once()->with($dto);
  $this->reEvaluateAction->shouldReceive('execute')->once()->with([
    ['entry_id' => 1],
    ['entry_id' => 2],
  ]);

  $this->service->removeItems($dto);
});

// --- اختبار التوابع المباشرة (One-liners) ---

it('shows offer details', function () {
  $this->showDetailsAction->shouldReceive('execute')->once()->with('slug')->andReturn(['id' => 1]);
  expect($this->service->show('slug'))->toBe(['id' => 1]);
});

it('indexes offers for a project', function () {
  $this->indexAction->shouldReceive('execute')->once()->with(1)->andReturn([]);
  expect($this->service->index(1))->toBeArray();
});

it('deletes an offer', function () {
  $this->deleteOffer->shouldReceive('execute')->once()->with('slug');
  $this->service->delete('slug');
});

it('activates and deactivates offers', function () {
  $dto = new ActivationOfferDTO('slug', true);

  $this->activateOffer->shouldReceive('execute')->once()->with($dto);
  $this->service->activate($dto);

  $this->deactivateOffer->shouldReceive('execute')->once()->with($dto);
  $this->service->deactivate($dto);
});

it('runs scheduled offers', function () {
  $this->action->shouldReceive('execute')->once()->andReturn(['processed' => 5]);
  expect($this->service->run())->toBe(['processed' => 5]);
});

it('subscribes a user to an offer', function () {
  $dto = new SubscribeDTO('slug', 'CODE123', 1, 1);
  $this->subscribeAction->shouldReceive('execute')->once()->with($dto);
  $this->service->subscribe($dto);
});

it('deletes offer prices when benefit type is quantity or total_price during update', function () {
  // 1. إعداد البيانات بحيث تحتوي على offerData لتشغيل سطر التحديث
  $dto = new UpdateOfferDTO('slug', [], ['benefit_type' => 'quantity']);

  $collection = ['id' => 10, 'type' => 'static'];
  $offer = ['id' => 20, 'benefit_type' => 'quantity'];

  // 2. إعداد الـ Mocks
  // نحتاج لمحاكاة جلب الـ Collection لأننا لم نمرر collectionData
  $this->cms->shouldReceive('getCollectionBySlug')
    ->once()
    ->with('slug')
    ->andReturn($collection);

  // ✅ هذا الجزء كان ناقصاً: محاكاة تنفيذ التحديث للـ Offer
  $this->updateOffer->shouldReceive('execute')
    ->once()
    ->with(10, $dto)
    ->andReturn($offer);

  // التوقع الأساسي للـ elseif
  $this->deleteOfferPricesAction->shouldReceive('execute')
    ->once()
    ->with(20);

  $result = $this->service->update($dto);

  expect($result)->toHaveKey('offer');
  expect($result['offer']['benefit_type'])->toBe('quantity');
});
