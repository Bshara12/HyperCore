<?php

use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
  // تشغيل دالة boot لتسجيل الـ Rate Limiters
  (new AppServiceProvider($this->app))->boot();
});

it('registers rate limiters with correct max attempts', function (string $name, int $expectedMaxAttempts) {
  // 1. جلب الـ Limiter المسجل بحسب الاسم
  $limiter = RateLimiter::limiter($name);

  expect($limiter)->not->toBeNull();

  // 2. محاكاة طلب تجريبي
  $request = Request::create('/api/test', 'GET');

  /** @var Limit $limit */
  $limit = $limiter($request);

  // 3. التحقق من الحد الأقصى للمحاولات
  expect($limit)->toBeInstanceOf(Limit::class);
  expect($limit->maxAttempts)->toBe($expectedMaxAttempts);
})->with([
  'standard rate limiter allows 60 attempts' => ['api.standard', 60],
  'heavy rate limiter allows 15 attempts'    => ['api.heavy', 15],
  'ai rate limiter allows 5 attempts'        => ['api.ai', 5],
]);

it('scopes rate limiters by user id when authenticated', function () {
  $limiter = RateLimiter::limiter('api.standard');

  // إنشاء طلب يحتوي على مستخدم مسجل الدخول
  $request = Request::create('/api/test', 'GET');
  $mockUser = (object) ['id' => 99];
  $request->setUserResolver(fn() => $mockUser);

  /** @var Limit $limit */
  $limit = $limiter($request);

  // التحقق من أن المفتاح المستخدم هو ID المستخدم
  expect($limit->key)->toBe(99);
});

it('scopes rate limiters by IP address when unauthenticated', function () {
  $limiter = RateLimiter::limiter('api.standard');

  // إنشاء طلب لزائر غير مسجل مع حديد الـ IP
  $request = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

  /** @var Limit $limit */
  $limit = $limiter($request);

  // التحقق من أن المفتاح المستخدم هو الـ IP
  expect($limit->key)->toBe('192.168.1.1');
});
