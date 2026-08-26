<?php

namespace Tests\Feature\Http\Controllers;

use App\Domains\Booking\Services\BookingAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    private MockInterface $serviceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // عمل Mock للخدمة لتجنب الاتصال المباشر بمنطق قاعدة البيانات
        $this->serviceMock = $this->mock(BookingAnalyticsService::class);

        $this->withHeaders(['Accept' => 'application/json']);
    }

    #[Test]
    public function it_returns_complete_overview_analytics_data_successfully()
    {
        // 1. تجهيز البيانات الوهمية لجميع الخدمات الخمسة
        $mockSummary = ['total_bookings' => 100, 'total_revenue' => 1500];
        $mockTrend = [['date' => '2026-05-01', 'count' => 5]];
        $mockResources = [['resource_id' => 1, 'name' => 'Room A', 'usage' => '80%']];
        $mockCancellations = ['cancelled_count' => 2];
        $mockPeakTimes = ['peak_hour' => '10:00'];

        // 2. توقع استدعاء كافة التوابع الخمسة داخل الـ Controller لمرة واحدة
        $this->serviceMock->shouldReceive('getOverview')->once()->andReturn($mockSummary);
        $this->serviceMock->shouldReceive('getTrend')->once()->andReturn($mockTrend);
        $this->serviceMock->shouldReceive('getResourcePerformance')->once()->andReturn($mockResources);
        $this->serviceMock->shouldReceive('getCancellationReport')->once()->andReturn($mockCancellations);
        $this->serviceMock->shouldReceive('getPeakTimes')->once()->andReturn($mockPeakTimes);

        // 3. تنفيذ الطلب باستخدام اسم المسار المعرف في api.php (booking.analytics.overview)
        $response = $this->getJson(route('booking.analytics.overview', ['project_id' => 1]));

        // 4. التحقق من نجاح الاستجابة ومطابقة هيكل البيانات المجمعة كاملة
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => $mockSummary,
                    'trend' => $mockTrend,
                    'resources' => $mockResources,
                    'cancellations' => $mockCancellations,
                    'peak-times' => $mockPeakTimes,
                ],
            ]);
    }

    #[Test]
    public function it_filters_overview_data_when_query_parameters_are_provided()
    {
        // توقع استدعاء كافة التوابع مع إمكانية تمرير فلاتر التاريخ والـ project_id
        $this->serviceMock->shouldReceive('getOverview')->once()->andReturn([]);
        $this->serviceMock->shouldReceive('getTrend')->once()->andReturn([]);
        $this->serviceMock->shouldReceive('getResourcePerformance')->once()->andReturn([]);
        $this->serviceMock->shouldReceive('getCancellationReport')->once()->andReturn([]);
        $this->serviceMock->shouldReceive('getPeakTimes')->once()->andReturn([]);

        // تنفيذ الطلب مع تمرير فلاتر الإحصائيات (مثل من تاريخ إلى تاريخ)
        $response = $this->getJson(route('booking.analytics.overview', [
            'project_id' => 1,
            'from' => '2026-05-01',
            'to' => '2026-05-31',
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'trend',
                    'resources',
                    'cancellations',
                    'peak-times',
                ],
            ]);
    }
}
