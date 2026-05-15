<?php

namespace Tests\Unit\Models;

use App\Models\Offer;
use App\Models\UserOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('belongs to an offer', function () {
  // 1. إنشاء العرض (Offer)
  // أضفنا benefit_type بناءً على الخطأ الأخير، و benefit_value كإجراء احتياطي
  $offer = Offer::create([
    'project_id'    => 1,
    'collection_id' => 100,
    'is_code_offer' => true,
    'offer_duration' => 7, // مدة العرض بالأيام
    'code'          => 'RAMADAN2026',
    'benefit_type'  => 'percentage', // القيمة التي كشفها الخطأ
    'benefit_config' => ['percentage' => 15],         // حقل متوقع وجوده دائماً مع النوع
  ]);

  // 2. إنشاء ربط العرض بالمستخدم (UserOffer)
  $userOffer = UserOffer::create([
    'offer_id'   => $offer->id,
    'user_id'    => 1,
    'project_id' => 1,
    'start_at'   => now(),
    'end_at'     => now()->addDays(7),
  ]);

  // 3. التحقق من العلاقة
  expect($userOffer->offer)->toBeInstanceOf(Offer::class)
    ->and($userOffer->offer->id)->toBe($offer->id);
});

it('correctly casts date attributes', function () {
  $start = now()->startOfMinute();
  $end = now()->addMonth()->startOfMinute();

  $userOffer = new UserOffer([
    'start_at' => $start,
    'end_at'   => $end,
  ]);

  expect($userOffer->start_at)->toBeInstanceOf(Carbon::class)
    ->and($userOffer->end_at)->toBeInstanceOf(Carbon::class);
});
