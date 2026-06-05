<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\TrackSubscriptionEvent;
use App\Domains\Subscription\Services\DomainEventService;
use Illuminate\Http\Request;
use Mockery;

// ─── 1. اختبار حالة فشل الطلب (Status >= 400) ──────────────────────────────
test('it does not track event if response status code is 400 or higher', function () {
  // تجهيز الـ Mock والتأكد من أن الدالة dispatch لن تُستدعى أبداً
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldNotReceive('dispatch');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST');

  // محاكاة استجابة خاطئة (مثلاً 400 Bad Request)
  $response = $middleware->handle($request, function ($req) {
    return response()->json(['error' => 'Bad Request'], 400);
  }, 'event.key');

  expect($response->getStatusCode())->toBe(400);
});

// ─── 2. اختبار حالة عدم وجود معرف المستخدم user_id ─────────────────────────
test('it does not track event if user_id is missing from request', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldNotReceive('dispatch');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST'); // طلب فارغ بدون مستخدم

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'event.key');

  expect($response->getStatusCode())->toBe(200);
});

// ─── 3. اختبار النجاح عند قراءة البيانات من المدخلات (Inputs) ───────────────
test('it tracks event with user_id and project_id from request inputs', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);

  // نتحقق من وصول المعاملات بنوع البيانات الصحيح (int)
  $serviceMock->shouldReceive('dispatch')
    ->once()
    ->with(123, 456, 'subscription.activated');

  $middleware = new TrackSubscriptionEvent($serviceMock);

  // تمرير البيانات كـ Inputs داخل الـ Request
  $request = Request::create('/any-route', 'POST', [
    'user_id' => '123',
    'project_id' => '456'
  ]);

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'subscription.activated');

  expect($response->getStatusCode())->toBe(200);
});

// ─── 4. اختبار النجاح عند قراءة البيانات من الخصائص (Attributes) ─────────────
test('it tracks event with user_id and project_id from request attributes', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldReceive('dispatch')
    ->once()
    ->with(789, 999, 'subscription.updated');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST');

  // تعيين البيانات كـ Dynamic Attributes (مثل التي تأتي من Middleware حماية سابق)
  $request->user_id = '789';
  $request->project_id = '999';

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'subscription.updated');

  expect($response->getStatusCode())->toBe(200);
});

// ─── 5. اختبار إرسال معرف المشروع كـ Null إذا كان مفقوداً ───────────────────
test('it tracks event with null project_id if project_id is missing', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldReceive('dispatch')
    ->once()
    ->with(123, null, 'subscription.cancelled');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST', [
    'user_id' => '123'
    // 'project_id' مفقود هنا
  ]);

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'subscription.cancelled');

  expect($response->getStatusCode())->toBe(200);
});
