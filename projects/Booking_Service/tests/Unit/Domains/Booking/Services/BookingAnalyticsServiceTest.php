<?php

namespace Tests\Unit\Domains\Booking\Services;

use App\Domains\Booking\Services\BookingAnalyticsService;
use App\Domains\Booking\Analytics\Actions\GetBookingOverviewAction;
use App\Domains\Booking\Analytics\Actions\GetBookingTrendAction;
use App\Domains\Booking\Analytics\Actions\GetResourcePerformanceAction;
use App\Domains\Booking\Analytics\Actions\GetCancellationReportAction;
use App\Domains\Booking\Analytics\Actions\GetPeakTimesAction;
use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use Mockery;

test('booking analytics service delegates methods to correct actions', function () {
  // 1. إنشاء الـ DTO الوهمي والبيانات المتوقع عودتها
  $dto = new AnalyticsFilterDTO('2026-04-01', '2026-04-30', 'daily', 1, 10);
  $expectedResponse = ['data' => 'test_results'];

  // 2. عمل Mock لكل الـ Actions
  $overviewMock = Mockery::mock(GetBookingOverviewAction::class);
  $trendMock = Mockery::mock(GetBookingTrendAction::class);
  $resourceMock = Mockery::mock(GetResourcePerformanceAction::class);
  $cancellationMock = Mockery::mock(GetCancellationReportAction::class);
  $peakTimesMock = Mockery::mock(GetPeakTimesAction::class);

  // 3. تحديد التوقعات (Expectations)
  $overviewMock->shouldReceive('execute')->once()->with($dto)->andReturn($expectedResponse);
  $trendMock->shouldReceive('execute')->once()->with($dto)->andReturn($expectedResponse);
  $resourceMock->shouldReceive('execute')->once()->with($dto)->andReturn($expectedResponse);
  $cancellationMock->shouldReceive('execute')->once()->with($dto)->andReturn($expectedResponse);
  $peakTimesMock->shouldReceive('execute')->once()->with($dto)->andReturn($expectedResponse);

  // 4. حقن الـ Mocks في الخدمة
  $service = new BookingAnalyticsService(
    $overviewMock,
    $trendMock,
    $resourceMock,
    $cancellationMock,
    $peakTimesMock
  );

  // 5. التنفيذ والتحقق
  expect($service->getOverview($dto))->toBe($expectedResponse);
  expect($service->getTrend($dto))->toBe($expectedResponse);
  expect($service->getResourcePerformance($dto))->toBe($expectedResponse);
  expect($service->getCancellationReport($dto))->toBe($expectedResponse);
  expect($service->getPeakTimes($dto))->toBe($expectedResponse);
});
