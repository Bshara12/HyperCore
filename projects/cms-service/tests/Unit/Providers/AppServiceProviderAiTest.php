<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use App\Domains\AI\Providers\AIProviderChain;
use App\Domains\AI\Providers\OpenRouterProvider;

/**
 * ─── Fake OpenRouter Provider ───
 * كلاس نقي يتخطى كود البناء الأصلي لـ OpenRouter لمنع أي تداخلات خارجية أو أخطاء إعدادات
 */
class FakeOpenRouterProvider extends OpenRouterProvider
{
  public function __construct() {}
}

// ─── كود الاختبار والتغطية الشاملة ───────────────────────────────────────

beforeEach(function () {
  // إنشاء الـ Provider وتشغيل دالة الـ register لتفعيل الروابط
  $this->provider = new AppServiceProvider(app());
  $this->provider->register();
});

test('it registers AIProviderChain as a singleton with OpenRouterProvider dependency', function () {
  // 1. ربط الاعتمادية المزيفة داخل الحاوية لضمان سلامة التنفيذ
  app()->bind(OpenRouterProvider::class, FakeOpenRouterProvider::class);

  // 2. طلب كائن AIProviderChain للمرة الأولى (هنا سيتم تنفيذ الـ Closure بالكامل)
  $firstResolution = app()->make(AIProviderChain::class);

  // 3. طلب الكائن للمرة الثانية للتحقق من خاصية الـ Singleton
  $secondResolution = app()->make(AIProviderChain::class);

  // 4. التأكيدات الصارمة (Assertions)
  expect($firstResolution)->toBeInstanceOf(AIProviderChain::class);

  // التأكد من أنه Singleton حقيقي (نفس المكان والمؤشر في الذاكرة)
  expect($firstResolution)->toBe($secondResolution);
});
