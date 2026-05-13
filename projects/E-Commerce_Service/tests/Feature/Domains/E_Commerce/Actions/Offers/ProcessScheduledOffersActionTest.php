<?php

use App\Domains\E_Commerce\Actions\Offers\ProcessScheduledOffersAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

it('processes scheduled offers and flushes offers cache tag', function () {
  // 1. تثبيت الوقت لضمان دقة المقارنة في الاختبار
  $frozenTime = Carbon::create(2026, 5, 11, 12, 0, 0);
  Carbon::setTestNow($frozenTime);

  // 2. إعداد البيانات الوهمية
  $activatedMock = collect([['id' => 1, 'status' => 'active']]);
  $deactivatedMock = collect([['id' => 2, 'status' => 'expired']]);

  // 3. بناء الـ Mock للـ Repository
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // التحقق من استدعاء التنشيط مع الوقت الحالي
  $repository->shouldReceive('getAndActivateDueOffers')
    ->once()
    ->with(Mockery::on(fn($time) => $time->equalTo($frozenTime)))
    ->andReturn($activatedMock->toArray());

  // التحقق من استدعاء الإيقاف مع الوقت الحالي
  $repository->shouldReceive('getAndDeactivateExpiredOffers')
    ->once()
    ->with(Mockery::on(fn($time) => $time->equalTo($frozenTime)))
    ->andReturn($deactivatedMock->toArray());

  // 4. محاكاة الكاش (Tags)
  // ملاحظة: Cache::tags تحتاج إلى تعريف Driver يدعمها (مثل redis أو array)
  Cache::shouldReceive('tags')
    ->once()
    ->with(['offers'])
    ->andReturnSelf();

  Cache::shouldReceive('flush')
    ->once();

  $action = new ProcessScheduledOffersAction($repository);

  // 5. التنفيذ
  $result = $action->execute();

  // 6. التحقق من النتائج
  expect($result['activated'])->toHaveCount(1);
  expect($result['deactivated'])->toHaveCount(1);
  expect($result['activated'][0]['id'])->toBe(1);

  // إعادة ضبط الوقت (Cleanup)
  Carbon::setTestNow();
});
