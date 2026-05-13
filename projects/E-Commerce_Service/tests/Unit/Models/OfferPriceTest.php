<?php

namespace Tests\Unit\Models;

use App\Models\Offer;
use App\Models\OfferPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts attributes to correct types in offer price', function () {
  // يجب إنشاء Offer أولاً لأن applied_offer_id مطلوب
  $offer = Offer::create(['project_id' => 1, 'collection_id' => 1, 'benefit_type' => 'test']);

  $offerPrice = OfferPrice::create([
    'applied_offer_id' => $offer->id,
    'entry_id' => 1,
    'original_price' => '150.505',
    'final_price' => 120,
    'is_applied' => 1,
    'is_code_price' => '0',
  ]);

  expect($offerPrice->original_price)->toBe("150.51")
    ->and($offerPrice->is_applied)->toBeTrue();
});

it('belongs to an offer using custom foreign key applied_offer_id', function () {
  $offer = Offer::create(['project_id' => 1, 'collection_id' => 1, 'benefit_type' => 'coupon']);

  $offerPrice = OfferPrice::create([
    'applied_offer_id' => $offer->id,
    'entry_id' => 50,
    'original_price' => 100,
    'final_price' => 80
  ]);

  expect($offerPrice->offer)->toBeInstanceOf(Offer::class)
    ->and($offerPrice->offer->id)->toBe($offer->id);
});