<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Offers\OfferPriceRepositoryEloquent;
use App\Models\Offer;
use App\Models\OfferPrice;
use App\Models\UserOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @var OfferPriceRepositoryEloquent|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new OfferPriceRepositoryEloquent();

  // محاكاة المستخدم في الـ Request
  $request = request();
  $request->attributes->set('auth_user', ['id' => 1]);
});

/**
 * 1. Test: enterOfferItem, getLowestPriceItem, disableItemPrice
 */
it('manages entry prices correctly', function () use (&$repository) {
  $offer = Offer::factory()->create(); // نحتاج ID عرض صالح

  $data = [
    'entry_id' => 10,
    'applied_offer_id' => $offer->id, // إضافة الحقل المطلوب لإصلاح خطأ Integrity constraint
    'original_price' => 100,
    'final_price' => 90,
    'is_applied' => true,
    'is_code_price' => false
  ];

  $repository->enterOfferItem($data);
  $this->assertDatabaseHas('offer_prices', $data);

  $lowest = $repository->getLowestPriceItem(10);
  expect($lowest->final_price)->toEqual(90);

  $repository->disableItemPrice(10);
  expect($lowest->refresh()->is_applied)->toBeFalse();
});

/**
 * 2. Test: Deletion Methods
 */
it('handles various deletion scenarios', function () use (&$repository) {
  $offer = Offer::factory()->create();
  $price = OfferPrice::factory()->create(['applied_offer_id' => $offer->id, 'entry_id' => 20]);

  $repository->deleteOfferPrice($price->id);
  $this->assertDatabaseMissing('offer_prices', ['id' => $price->id]);

  $price2 = OfferPrice::factory()->create(['applied_offer_id' => $offer->id]);
  $repository->deleteOfferPricesForOffer($offer->id);
  $this->assertDatabaseMissing('offer_prices', ['applied_offer_id' => $offer->id]);

  $price3 = OfferPrice::factory()->create(['entry_id' => 30, 'applied_offer_id' => $offer->id]);
  $repository->deleteOfferPriceForEntryAndProject(30, $offer->id);
  $this->assertDatabaseMissing('offer_prices', ['entry_id' => 30, 'applied_offer_id' => $offer->id]);

  $repository->deleteOfferPriceForEntryAndProject(999, 999);
});

/**
 * 3. Test: getEntryPrice
 */
it('can retrieve a specific entry price', function () use (&$repository) {
  $offer = Offer::factory()->create();
  OfferPrice::factory()->create(['entry_id' => 40, 'applied_offer_id' => $offer->id]);

  $price = $repository->getEntryPrice(40, $offer->id);
  expect($price)->not->toBeNull()
    ->and($price->entry_id)->toBe(40);
});

/**
 * 5. Test: getCodePrices
 */
it('returns prices for a specific valid code', function () use (&$repository) {
  $offer = Offer::factory()->create(['code' => 'TEST_CODE', 'is_active' => true]);

  // الحل: تمرير الـ ID للحقل الذي يبحث عنه المستودع في دالة getCodePrices
  OfferPrice::factory()->create([
    'entry_id' => 60,
    'applied_offer_id' => $offer->id, // وهذا الحقل المطلوب لقاعدة البيانات (NOT NULL)
    'is_code_price' => true,
    'is_applied' => true
  ]);

  $prices = $repository->getCodePrices([60], 'TEST_CODE');

  expect($prices)->toHaveCount(1);

  $empty = $repository->getCodePrices([60], 'WRONG');
  expect($empty)->toBeEmpty();
});

/**
 * 6. Test: getUserPrices
 */
/**
 * Test: getUserPrices 
 * نختبر قدرة المستودع على جلب الأسعار للأدوات المشترك فيها المستخدم
 */
it('retrieves the best prices for user-subscribed offers', function () use (&$repository) {
  $userId = 1;

  // إنشاء عرضين مختلفين لتجنب خطأ الـ Unique Constraint
  $offer1 = Offer::factory()->create();
  $offer2 = Offer::factory()->create();

  // اشتراك المستخدم في كلا العرضين
  UserOffer::create([
    'user_id' => $userId,
    'offer_id' => $offer1->id,
    'project_id' => 1,
    'start_at' => now(),
    'end_at' => now()->addDay()
  ]);

  UserOffer::create([
    'user_id' => $userId,
    'offer_id' => $offer2->id,
    'project_id' => 1,
    'start_at' => now(),
    'end_at' => now()->addDay()
  ]);

  $entryId = 70;

  // سعر من العرض الأول
  OfferPrice::factory()->create([
    'entry_id' => $entryId,
    'applied_offer_id' => $offer1->id,
    'final_price' => 100,
    'is_applied' => true
  ]);

  // سعر من العرض الثاني لنفس العنصر (مسموح لأن الـ offer_id مختلف)
  OfferPrice::factory()->create([
    'entry_id' => $entryId,
    'applied_offer_id' => $offer2->id,
    'final_price' => 50, // السعر الأفضل الذي يجب أن يعود
    'is_applied' => true
  ]);

  $results = $repository->getUserPrices([$entryId]);

  expect($results)->toHaveCount(1);
  // التأكد من أن التابع اختار السعر الأرخص (50) من بين العروض المشترك بها المستخدم
  expect($results[$entryId]->final_price)->toEqual(50);
});

/**
 * Test: getAutomaticPrices
 * نطبق نفس المنطق هنا لتجنب تكرار الـ entry_id مع نفس الـ offer_id
 */
it('returns the lowest applied automatic prices for entries', function () use (&$repository) {
  $entryId = 50;
  $offerA = Offer::factory()->create();
  $offerB = Offer::factory()->create();

  OfferPrice::factory()->create([
    'entry_id' => $entryId,
    'applied_offer_id' => $offerA->id,
    'final_price' => 300,
    'is_applied' => true,
    'is_code_price' => false
  ]);

  OfferPrice::factory()->create([
    'entry_id' => $entryId,
    'applied_offer_id' => $offerB->id,
    'final_price' => 250,
    'is_applied' => true,
    'is_code_price' => false
  ]);

  $results = $repository->getAutomaticPrices([$entryId]);

  expect($results)->toHaveCount(1)
    ->and($results[$entryId]->final_price)->toEqual(250);
});

it('covers deletion of price for entry and project branches', function () use (&$repository) {
  $offer = Offer::factory()->create();
  OfferPrice::factory()->create(['entry_id' => 80, 'applied_offer_id' => $offer->id]);

  // حالة الوجود
  $repository->deleteOfferPriceForEntryAndProject(80, $offer->id);
  $this->assertDatabaseMissing('offer_prices', ['entry_id' => 80]);

  // حالة عدم الوجود (تغطية السطر الذي لا ينفذ الـ delete)
  $repository->deleteOfferPriceForEntryAndProject(999, 999);
});
