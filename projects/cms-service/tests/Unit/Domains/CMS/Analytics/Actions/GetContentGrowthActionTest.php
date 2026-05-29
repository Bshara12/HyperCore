<?php

namespace Tests\Feature\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\Actions\GetContentGrowthAction;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project; // تأكد من استدعاء المودل هنا ⭐
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
    // 1. محاكاة الـ currentProject المربوط في حاوية لارافل
    $this->mockedCurrentProject = (object) ['public_id' => 'proj_abc123'];
    app()->instance('currentProject', $this->mockedCurrentProject);

    // 2. إنشاء كائن حقيقي من المودل لإرضاء الـ Return Type المتوقع ⭐
    $mockProject = new Project();
    $mockProject->id = 99; // تعيين المعرف المتوقع

    // محاكاة الـ ProjectRepositoryInterface ليعود بالمودل المتوافق
    $this->projectRepoMock = Mockery::mock(ProjectRepositoryInterface::class);
    $this->projectRepoMock->shouldReceive('findByKey')
        ->with('proj_abc123')
        ->andReturn($mockProject); // 🟢 تم استبدال stdClass بالمودل الحقيقي هنا
    app()->instance(ProjectRepositoryInterface::class, $this->projectRepoMock);

    // 3. محاكاة الـ AnalyticsRepositoryInterface وتجهيز الـ Action
    $this->analyticsRepoMock = Mockery::mock(AnalyticsRepositoryInterface::class);
    $this->action = new GetContentGrowthAction($this->analyticsRepoMock);
});

afterEach(function () {
    Mockery::close();
});

// 🧠 --- قسم اختبار الـ GetContentGrowthAction ونظام الكاش ---

test('it returns data from repository and caches it, then hits cache on subsequent calls', function () {
    $dto = new AnalyticsFilterDTO(
        from: '2026-01-01',
        to: '2026-01-31',
        period: 'monthly',
        projectId: 99,
        limit: 10
    );
    
    $expectedData = ['growth_rate' => '15%', 'total_new_content' => 120];
    $cacheKey = "analytics:project:99:content_growth:monthly:2026-01-01:2026-01-31";

    Cache::forget($cacheKey);

    $this->analyticsRepoMock->shouldReceive('getContentGrowth')
        ->once()
        ->with($dto)
        ->andReturn($expectedData);

    $firstResult = $this->action->execute($dto);
    expect($firstResult)->toBe($expectedData);
    expect(Cache::has($cacheKey))->toBeTrue();

    $secondResult = $this->action->execute($dto);
    expect($secondResult)->toBe($expectedData);
});


// 📋 --- قسم اختبار الـ AnalyticsFilterDTO وتغطية حالات الـ Request ---

test('it creates DTO from request with full explicit parameters', function () {
    $request = Request::create('/analytics/growth', 'GET', [
        'from' => '2026-05-01',
        'to' => '2026-05-27',
        'period' => 'weekly',
        'limit' => 25
    ]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    expect($dto->from)->toBe('2026-05-01')
        ->and($dto->to)->toBe('2026-05-27')
        ->and($dto->period)->toBe('weekly')
        ->and($dto->projectId)->toBe(99) 
        ->and($dto->limit)->toBe(25);
});

test('it creates DTO with system default values when request is empty', function () {
    $request = Request::create('/analytics/growth', 'GET'); 

    $dto = AnalyticsFilterDTO::fromRequest($request);

    expect($dto->from)->toBe(now()->subMonth()->format('Y-m-d'))
        ->and($dto->to)->toBe(now()->format('Y-m-d'))
        ->and($dto->period)->toBe('daily')
        ->and($dto->projectId)->toBe(99)
        ->and($dto->limit)->toBe(10); 
});

test('it forces daily period fallback if an invalid period is sent', function () {
    $request = Request::create('/analytics/growth', 'GET', [
        'period' => 'hourly' 
    ]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    expect($dto->period)->toBe('daily');
});