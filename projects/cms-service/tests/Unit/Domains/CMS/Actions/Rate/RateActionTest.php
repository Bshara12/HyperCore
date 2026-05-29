<?php

namespace Tests\Unit\Domains\CMS\Actions\Rate;

use App\Domains\CMS\Actions\Rate\RateAction;
use App\Domains\CMS\DTOs\Rate\RateDTO;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\RatingRepositoryInterface;
use App\Events\SystemLogEvent;
use App\Models\Project;
use App\Models\DataEntry; // ⭐ استيراد موديل الـ DataEntry الصحيح
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
  $this->ratingRepo = Mockery::mock(RatingRepositoryInterface::class);
  $this->projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
  $this->dataEntryRepo = Mockery::mock(DataEntryRepositoryInterface::class);

  $this->action = new RateAction(
    $this->ratingRepo,
    $this->projectRepo,
    $this->dataEntryRepo
  );
});

afterEach(function () {
  Mockery::close();
});

test('it creates a new rating and updates stats for a project successfully', function () {
  $projectId = 10;
  $dto = new RateDTO(1, 'project', 10, 5, 'Great project!');

  $project = new Project();
  $project->id = $projectId;
  $this->app->instance('currentProject', $project);

  // ⭐ حل مشكلة الكاش و Event::fake معاُ
  Cache::shouldReceive('forget')->once();
  Cache::shouldReceive('refreshEventDispatcher')->zeroOrMoreTimes(); // يسمح لـ لارفل بتحديث الـ Dispatcher داخلياً دون أخطاء

  Event::fake([SystemLogEvent::class]);

  // باقي التوقعات
  DB::shouldReceive('beginTransaction')->once();
  DB::shouldReceive('commit')->once();

  $this->ratingRepo->shouldReceive('findUserRating')->andReturn(null);
  $this->ratingRepo->shouldReceive('create')->once();

  $stats = (object) ['count' => 1, 'avg' => 5.0];
  $this->ratingRepo->shouldReceive('getStats')->andReturn($stats);

  $this->projectRepo->shouldReceive('findById')->with(10)->andReturn($project);
  $this->projectRepo->shouldReceive('updateRatingStats')->once();

  $result = $this->action->execute($dto);

  expect($result)->toBeTrue();
});

test('it throws exception if rating project outside current context', function () {
  $project = new Project();
  $project->id = 10;
  $this->app->instance('currentProject', $project);

  $dto = new RateDTO(1, 'project', 99, 5, null);

  DB::shouldReceive('beginTransaction')->once();
  DB::shouldReceive('rollBack')->once();

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'You cannot rate a project outside current context');
});

test('it throws exception if data entry does not belong to project', function () {
  $project = new Project();
  $project->id = 10;
  $this->app->instance('currentProject', $project);

  $dto = new RateDTO(1, 'data', 500, 5, null);

  // ⭐ حل مشكلة الـ TypeError: إنشاء موديل حقيقي يتوافق مع الـ Interface
  $wrongData = new DataEntry();
  $wrongData->project_id = 99; // مشروع مختلف عن 10

  $this->dataEntryRepo->shouldReceive('findOrFail')->with(500)->andReturn($wrongData);

  DB::shouldReceive('beginTransaction')->once();
  DB::shouldReceive('rollBack')->once();

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'This data does not belong to this project');
});

test('it throws exception for invalid rateable type fallback', function () {
  // 1. إعداد السياق (CurrentProject) لأن التابع execute يستدعيه أولاً
  $project = new Project();
  $project->id = 10;
  $this->app->instance('currentProject', $project);

  // 2. إنشاء DTO بنوع غير مدعوم لتشغيل مسار الـ fallback الـمستهدف
  $dto = new RateDTO(
    userId: 1,
    rateableType: 'unsupported_type', // نوع عشوائي غير project وغير data
    rateableId: 99,
    rating: 5,
    review: null
  );

  // 3. محاكاة الـ DB (يبدأ الـ Transaction ويتراجع فوراً عند رمي الخطأ)
  DB::shouldReceive('beginTransaction')->once();
  DB::shouldReceive('rollBack')->once();

  // 4. التنفيذ والتأكيد على رمي الاستثناء الصحيح
  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Invalid rateable type');
});

test('it creates a new rating and updates stats for data successfully', function () {
  $projectId = 10;
  $dataId = 500;
  $dto = new RateDTO(1, 'data', $dataId, 4, 'Good data entry');

  // 1. إعداد السياق (المشروع الحالي ID = 10)
  $project = new Project();
  $project->id = $projectId;
  $this->app->instance('currentProject', $project);

  // 2. إنشاء DataEntry تابعة لنفس المشروع (ID = 10) لتخطي الشرط والوصول للـ return
  $validData = new DataEntry();
  $validData->project_id = $projectId;
  $this->dataEntryRepo->shouldReceive('findOrFail')->with($dataId)->andReturn($validData);

  // 3. إعداد الكاش والـ Events
  Cache::shouldReceive('forget')->once();
  Cache::shouldReceive('refreshEventDispatcher')->zeroOrMoreTimes();
  Event::fake([SystemLogEvent::class]);

  // 4. محاكاة الـ DB والـ Repositories
  DB::shouldReceive('beginTransaction')->once();
  DB::shouldReceive('commit')->once();

  $this->ratingRepo->shouldReceive('findUserRating')->andReturn(null);
  $this->ratingRepo->shouldReceive('create')->once();

  $stats = (object) ['count' => 1, 'avg' => 4.0];
  $this->ratingRepo->shouldReceive('getStats')->andReturn($stats);

  // ⭐ التأكد من تحديث إحصائيات الـ data Entries (تطابقاً مع الـ match block)
  $this->dataEntryRepo->shouldReceive('updateRatingStats')->once();

  // التنفيذ
  $result = $this->action->execute($dto);

  expect($result)->toBeTrue();
});

test('it throws InvalidArgumentException in updateStats default match arm', function () {
  // 1. DTO بنوع غير مدعوم تماماً
  $dto = new RateDTO(
    userId: 1,
    rateableType: 'ghost_type',
    rateableId: 99,
    rating: 5,
    review: null
  );

  // 2. محاكاة دالة getStats لأنها تنفذ داخل updateStats قبل الـ match
  $stats = (object) ['count' => 1, 'avg' => 4.5];
  $this->ratingRepo->shouldReceive('getStats')
    ->with('ghost_type', 99)
    ->andReturn($stats);

  // 3. السحر: استخدام Reflection لكسر حماية الدالة private واستدعائها مباشرة
  $reflection = new \ReflectionClass(RateAction::class);
  $method = $reflection->getMethod('updateStats');
  $method->setAccessible(true); // جعل الدالة قابلة للاستدعاء الخارجي في بيئة الفحص

  // 4. التنفيذ والتأكيد على رمي الاستثناء المتواجد في السطر 174
  expect(fn() => $method->invoke($this->action, $dto))
    ->toThrow(\InvalidArgumentException::class, 'Invalid rateable type: ghost_type');
});

test('it updates an existing rating instead of creating a new one', function () {
  $projectId = 10;
  $dto = new RateDTO(1, 'project', 10, 5, 'Updating my previous rating!');

  // 1. إعداد السياق (المشروع الحالي)
  $project = new Project();
  $project->id = $projectId;
  $this->app->instance('currentProject', $project);

  // 2. محاكاة وجود تقييم مسبق (بإرجاع كائن يحتوي على ID)
  $existingRating = (object) ['id' => 42];
  $this->ratingRepo->shouldReceive('findUserRating')
    ->with(1, 'project', 10)
    ->andReturn($existingRating);

  // 3. ⭐ التأكيد على استدعاء دالة التحديث (update) وليس الإنشاء (create)
  $this->ratingRepo->shouldReceive('update')
    ->once()
    ->with($existingRating, $dto);

  // 4. إعداد الكاش والـ Events والـ DB
  Cache::shouldReceive('forget')->once();
  Cache::shouldReceive('refreshEventDispatcher')->zeroOrMoreTimes();
  Event::fake([SystemLogEvent::class]);

  DB::shouldReceive('beginTransaction')->once();
  DB::shouldReceive('commit')->once();

  // 5. محاكاة جلب الإحصائيات وتحديثها للمشروع
  $stats = (object) ['count' => 1, 'avg' => 5.0];
  $this->ratingRepo->shouldReceive('getStats')->andReturn($stats);
  $this->projectRepo->shouldReceive('findById')->with(10)->andReturn($project);
  $this->projectRepo->shouldReceive('updateRatingStats')->once();

  // التنفيذ
  $result = $this->action->execute($dto);

  // التأكيد
  expect($result)->toBeTrue();

  // التأكيد على أن الحدث أرسل الـ entityId الصحيح للتقييم المحدث (42)
  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->entityId === 42;
  });
});
