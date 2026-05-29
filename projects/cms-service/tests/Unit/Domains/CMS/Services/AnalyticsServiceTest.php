<?php

use App\Domains\CMS\Analytics\Actions\GetAdminOverviewAction;
use App\Domains\CMS\Analytics\Actions\GetContentGrowthAction;
use App\Domains\CMS\Analytics\Actions\GetContentSummaryAction;
use App\Domains\CMS\Analytics\Actions\GetProjectsGrowthAction;
use App\Domains\CMS\Analytics\Actions\GetRatingsReportAction;
use App\Domains\CMS\Analytics\Actions\GetTopRatedEntriesAction;
use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Services\AnalyticsService;

beforeEach(function () {
  // 💡 عمل ميوك لجميع الـ Actions التي تعتمد عليها الخدمة
  $this->adminOverview     = Mockery::mock(GetAdminOverviewAction::class);
  $this->projectsGrowth    = Mockery::mock(GetProjectsGrowthAction::class);
  $this->contentSummary    = Mockery::mock(GetContentSummaryAction::class);
  $this->contentGrowth     = Mockery::mock(GetContentGrowthAction::class);
  $this->topRatedEntries   = Mockery::mock(GetTopRatedEntriesAction::class);
  $this->ratingsReport     = Mockery::mock(GetRatingsReportAction::class);

  // حقن الـ Mocks داخل الخدمة
  $this->service = new AnalyticsService(
    $this->adminOverview,
    $this->projectsGrowth,
    $this->contentSummary,
    $this->contentGrowth,
    $this->topRatedEntries,
    $this->ratingsReport
  );
});

afterEach(function () {
  Mockery::close();
});

// ─── اختبارات التوابع التي تستخدم AdminOverviewDTO ───────────────────

test('adminOverview proxies request to GetAdminOverviewAction', function () {
  $dto = new AdminOverviewDTO(from: '2026-01-01', to: '2026-01-31', period: 'daily');

  $this->adminOverview
    ->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['overview' => 'data']);

  $result = $this->service->adminOverview($dto);

  expect($result)->toBe(['overview' => 'data']);
});

test('projectsGrowth proxies request to GetProjectsGrowthAction', function () {
  $dto = new AdminOverviewDTO(from: '2026-01-01', to: '2026-01-31', period: 'monthly');

  $this->projectsGrowth
    ->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['growth' => 'data']);

  $result = $this->service->projectsGrowth($dto);

  expect($result)->toBe(['growth' => 'data']);
});

// ─── اختبارات التوابع التي تستخدم AnalyticsFilterDTO ──────────────────

test('contentSummary proxies request to GetContentSummaryAction', function () {
  $dto = new AnalyticsFilterDTO(from: '2026-01-01', to: '2026-01-31', period: 'daily', projectId: 5, limit: 10);

  $this->contentSummary
    ->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['summary' => 'data']);

  $result = $this->service->contentSummary($dto);

  expect($result)->toBe(['summary' => 'data']);
});

test('contentGrowth proxies request to GetContentGrowthAction', function () {
  $dto = new AnalyticsFilterDTO(from: '2026-01-01', to: '2026-01-31', period: 'weekly', projectId: 5, limit: 10);

  $this->contentGrowth
    ->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['content_growth' => 'data']);

  $result = $this->service->contentGrowth($dto);

  expect($result)->toBe(['content_growth' => 'data']);
});

test('topRatedEntries proxies request to GetTopRatedEntriesAction', function () {
  $dto = new AnalyticsFilterDTO(from: '2026-01-01', to: '2026-01-31', period: 'daily', projectId: 5, limit: 5);

  $this->topRatedEntries
    ->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['top_rated' => 'data']);

  $result = $this->service->topRatedEntries($dto);

  expect($result)->toBe(['top_rated' => 'data']);
});

test('ratingsReport proxies request to GetRatingsReportAction', function () {
  $dto = new AnalyticsFilterDTO(from: '2026-01-01', to: '2026-01-31', period: 'monthly', projectId: 5, limit: 20);

  $this->ratingsReport
    ->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['ratings' => 'data']);

  $result = $this->service->ratingsReport($dto);

  expect($result)->toBe(['ratings' => 'data']);
});
