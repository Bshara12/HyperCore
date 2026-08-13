<?php

namespace Tests\Feature\Services;

use App\Services\OTPService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
  // تنظيف الكاش بالكامل قبل كل تيست لضمان عزل البيانات وعدم تداخلها
  Cache::flush();

  $this->otpService = new OTPService();
});

// =========================================================================
// 1. اختبار دالة: generate
// =========================================================================

test('generate method creates a 6-digit random code and stores it in cache', function () {
  $mockedUser = (object) ['id' => 99];

  // 1. استدعاء الدالة لتوليد الرمز
  $code = $this->otpService->generate($mockedUser);

  // 2. التحقق من أن الرمز يتكون من 6 أرقام
  expect($code)->toBeInt()
    ->toBeGreaterThanOrEqual(100000)
    ->toBeLessThanOrEqual(999999);

  // 3. التحقق من أن الرمز تم تخزينه في الكاش بشكل صحيح ومربوط بـ ID المستخدم
  expect(Cache::get("otp_99"))->toBe($code);
});

// =========================================================================
// 2. اختبار دالة: verify
// =========================================================================

test('verify method returns true when code matches the cached value', function () {
  $mockedUser = (object) ['id' => 55];

  // شحن الكاش مسبقاً برمز افتراضي (يخزن حقيقةً في الذاكرة المؤقتة للاختبار)
  Cache::put("otp_55", 123456, now()->addMinutes(10));

  // استدعاء التحقق برقم مطابق
  $result = $this->otpService->verify($mockedUser, 123456);

  expect($result)->toBeTrue();
});

test('verify method returns false when code does not match the cached value', function () {
  $mockedUser = (object) ['id' => 55];

  // شحن الكاش برمز
  Cache::put("otp_55", 123456, now()->addMinutes(10));

  // محاولة التحقق برقم خاطئ
  $result = $this->otpService->verify($mockedUser, 654321);

  expect($result)->toBeFalse();
});

test('verify method returns false when otp has expired or does not exist in cache', function () {
  $mockedUser = (object) ['id' => 77];

  // هنا الكاش فارغ تماماً لنفس المستخدم
  $result = $this->otpService->verify($mockedUser, 123456);

  expect($result)->toBeFalse();
});
