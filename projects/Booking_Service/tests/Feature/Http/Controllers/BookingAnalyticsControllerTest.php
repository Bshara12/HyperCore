<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\Booking\Services\BookingAnalyticsService;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class BookingAnalyticsControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $serviceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->serviceMock = $this->mock(BookingAnalyticsService::class);

    // محاكاة وجود قيمة للمشروع في الـ Request لضمان نجاح الـ DTO
    // الـ DTO يبدو أنه يتوقع وجود project_id في الطلب
    $this->withHeaders(['Accept' => 'application/json']);
  }
  #[Test]
  public function it_returns_overview_data()
  {
    $mockData = ['total' => 10];
    $this->serviceMock->shouldReceive('getOverview')->once()->andReturn($mockData);

    // نمرر project_id كـ Query Parameter ليتمكن الـ DTO من قراءته
    $response = $this->getJson(route('analytics.overview', ['project_id' => 1]));

    $response->assertStatus(200)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }
  #[Test]
  public function it_returns_trend_data()
  {
    $mockData = ['trend' => []];
    $this->serviceMock->shouldReceive('getTrend')->once()->andReturn($mockData);

    $response = $this->getJson(route('analytics.trend', ['project_id' => 1]));

    $response->assertStatus(200)->assertJson(['success' => true]);
  }
  #[Test]
  public function it_returns_resource_performance_data()
  {
    $mockData = ['perf' => []];
    $this->serviceMock->shouldReceive('getResourcePerformance')->once()->andReturn($mockData);

    $response = $this->getJson(route('analytics.resources', ['project_id' => 1]));

    $response->assertStatus(200)->assertJson(['success' => true]);
  }
  #[Test]
  public function it_returns_cancellations_report()
  {
    $mockData = ['cancels' => []];
    $this->serviceMock->shouldReceive('getCancellationReport')->once()->andReturn($mockData);

    $response = $this->getJson(route('analytics.cancellations', ['project_id' => 1]));

    $response->assertStatus(200)->assertJson(['success' => true]);
  }
  #[Test]
  public function it_returns_peak_times_data()
  {
    $mockData = ['peaks' => []];
    $this->serviceMock->shouldReceive('getPeakTimes')->once()->andReturn($mockData);

    $response = $this->getJson(route('analytics.peak-times', ['project_id' => 1]));

    $response->assertStatus(200)->assertJson(['success' => true]);
  }
}
