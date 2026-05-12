<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Offers\OfferRepositoryEloquent;
use App\Domains\E_Commerce\DTOs\Offers\SubscribeDTO;
use App\Models\Offer;
use App\Models\OfferPrice;
use App\Models\UserOffer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @var OfferRepositoryEloquent|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new OfferRepositoryEloquent();
});

/**
 * 1. Test: create, update, findByCollectionId
 */
it('covers basic crud operations', function () use (&$repository) {
  // Create
  $offer = $repository->create(101, [
    'project_id' => 1,
    'benefit_type' => 'fixed',
    'is_active' => true
  ]);
  expect($offer->collection_id)->toBe(101);

  // Find
  $found = $repository->findByCollectionId(101);
  expect($found->id)->toBe($offer->id);

  // Update
  $updated = $repository->update(101, ['benefit_type' => 'percentage']);
  expect($updated->benefit_type)->toBe('percentage');
});

/**
 * 2. Test: reEvaluate
 */
it('re-evaluates and toggles is_applied based on lowest price', function () use (&$repository) {
  $entryId = 55;
  // سعر مرتفع مطبق حالياً
  OfferPrice::factory()->create(['entry_id' => $entryId, 'final_price' => 200, 'is_applied' => true, 'is_code_price' => false]);
  // سعر منخفض غير مطبق
  OfferPrice::factory()->create(['entry_id' => $entryId, 'final_price' => 150, 'is_applied' => false, 'is_code_price' => false]);

  $repository->reEvaluate($entryId);

  $this->assertDatabaseHas('offer_prices', ['entry_id' => $entryId, 'final_price' => 150, 'is_applied' => true]);
  $this->assertDatabaseHas('offer_prices', ['entry_id' => $entryId, 'final_price' => 200, 'is_applied' => false]);
});

/**
 * 3. Test: getOfferDetails & getProjectOffers
 */
it('retrieves offer details and project offers', function () use (&$repository) {
  Offer::factory()->create(['collection_id' => 202, 'project_id' => 7]);

  expect($repository->getOfferDetails(202))->not->toBeNull();
  expect($repository->getProjectOffers(7))->toHaveCount(1);
});

/**
 * 4. Test: deleteOfferByCollectionId (All Branches)
 */
it('throws exception if deleting already trashed offer', function () use (&$repository) {
  $offer = Offer::factory()->create(['collection_id' => 303]);
  $offer->delete();

  expect(fn() => $repository->deleteOfferByCollectionId(303))
    ->toThrow(DomainException::class, "This offer was deleted previously");
});

it('deactivates and soft deletes an offer', function () use (&$repository) {
  $offer = Offer::factory()->create(['collection_id' => 404, 'is_active' => true]);

  $repository->deleteOfferByCollectionId(404);

  $this->assertSoftDeleted('offers', ['id' => $offer->id]);
  $this->assertDatabaseHas('offers', ['id' => $offer->id, 'is_active' => false]);
});

/**
 * 5. Test: activate & deactivate (Including null check branch)
 */
it('activates and deactivates offers', function () use (&$repository) {
  $offer = Offer::factory()->create(['collection_id' => 505, 'is_active' => true]);

  $repository->deactivateOffer(505);
  expect($offer->refresh()->is_active)->toBeFalse();

  $repository->activateOffer(505);
  expect($offer->refresh()->is_active)->toBeTrue();

  // Test with non-existent ID to cover the "if ($offer)" branch
  $repository->deactivateOffer(9999);
  $repository->activateOffer(9999);
});

/**
 * 6. Test: getAndActivateDueOffers
 */
it('activates due offers', function () use (&$repository) {
  $now = Carbon::now();
  $offer = Offer::factory()->create([
    'is_active' => false,
    'start_at' => $now->copy()->subMinute()
  ]);
  OfferPrice::factory()->create(['applied_offer_id' => $offer->id, 'entry_id' => 111]);

  $ids = $repository->getAndActivateDueOffers($now);

  expect($ids)->toContain(111);
  expect($offer->refresh()->is_active)->toBeTrue();
});

/**
 * 7. Test: getAndDeactivateExpiredOffers
 */
it('deactivates expired offers and purges prices', function () use (&$repository) {
  $now = Carbon::now();
  $offer = Offer::factory()->create([
    'is_active' => true,
    'end_at' => $now->copy()->subMinute()
  ]);
  OfferPrice::factory()->create(['applied_offer_id' => $offer->id, 'entry_id' => 222]);

  $ids = $repository->getAndDeactivateExpiredOffers($now);

  expect($ids)->toContain(222);
  expect($offer->refresh()->is_active)->toBeFalse();
  $this->assertDatabaseMissing('offer_prices', ['applied_offer_id' => $offer->id]);
});

/**
 * 8. Test: subscribe (All Exception Branches)
 */
it('throws exception if offer does not exist during subscribe', function () use (&$repository) {
  $dto = new SubscribeDTO('slug', 'code', 1, 1);
  expect(fn() => $repository->subscribe(9999, $dto))
    ->toThrow(DomainException::class, "Offer doesn't exist");
});

it('throws exception if code is invalid', function () use (&$repository) {
  Offer::factory()->code('REAL')->create(['collection_id' => 606]);
  $dto = new SubscribeDTO('slug', 'WRONG', 1, 1);

  expect(fn() => $repository->subscribe(606, $dto))
    ->toThrow(DomainException::class, "Invalid or expired code");
});

it('throws exception if user already subscribed', function () use (&$repository) {
  $offer = Offer::factory()->code('REUSE')->create(['collection_id' => 707]);
  UserOffer::create([
    'offer_id' => $offer->id,
    'user_id' => 1,
    'project_id' => 1,
    'start_at' => now(),
    'end_at' => now()->addDays(1)
  ]);

  $dto = new SubscribeDTO('slug', 'REUSE', 1, 1);
  expect(fn() => $repository->subscribe(707, $dto))
    ->toThrow(DomainException::class, "This offer has already been subscribed to");
});

it('successfully subscribes user to offer', function () use (&$repository) {
  $offer = Offer::factory()->code('SUCCESS')->create(['collection_id' => 808, 'offer_duration' => 10]);
  $dto = new SubscribeDTO('slug', 'SUCCESS', 5, 1);

  $repository->subscribe(808, $dto);

  $this->assertDatabaseHas('user_offers', [
    'offer_id' => $offer->id,
    'user_id' => 5
  ]);
});
