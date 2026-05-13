<?php

namespace Tests\Unit\Models;

use App\Models\Offer;
use App\Models\OfferPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

uses(RefreshDatabase::class);

it('casts attributes to correct types', function () {
  $offer = Offer::create([
    'project_id' => 1,
    'collection_id' => 1,
    'benefit_type' => 'discount',
    'benefit_config' => ['discount' => 10],
    'is_active' => 1,
    'is_code_offer' => 0,
  ]);

  expect($offer->benefit_config)->toBeArray()
    ->and($offer->is_active)->toBeTrue();
});

it('has many offer prices', function () {
  // 1. إنشاء العرض
  $offer = Offer::create([
    'project_id' => 1,
    'collection_id' => 1,
    'benefit_type' => 'fixed',
  ]);

  // 2. إنشاء السعر وربطه يدوياً للتأكد من SQL
  OfferPrice::create([
    'applied_offer_id' => $offer->id,
    'entry_id' => 100,
    'original_price' => 200,
    'final_price' => 150
  ]);

  // 3. إعادة تحميل العرض مع العلاقات لضمان نظافة البيانات في الذاكرة
  $offer->load('offer_price');

  expect($offer->offer_price)->toBeInstanceOf(Collection::class)
    ->and($offer->offer_price)->toHaveCount(1)
    ->and($offer->offer_price->first()->applied_offer_id)->toBe($offer->id);
});

it('implements soft deletes', function () {
  $offer = Offer::create([
    'project_id' => 1,
    'collection_id' => 1,
    'benefit_type' => 'test'
  ]);

  $offer->delete();

  expect($offer->deleted_at)->not->toBeNull();
});
