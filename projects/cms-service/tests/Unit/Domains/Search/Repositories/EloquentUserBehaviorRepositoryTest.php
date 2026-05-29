<?php

use App\Domains\Search\DTOs\LogClickDTO;
use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\Models\UserClickLog;
use App\Domains\Search\Repositories\Eloquent\EloquentUserBehaviorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// uses(RefreshDatabase::class);

// beforeEach(function () {
//   $this->repository = new EloquentUserBehaviorRepository();

//   // حل مشكلة DB::raw
//   DB::shouldReceive('raw')->andReturnUsing(fn($value) => $value);
// });

// test('logSearch creates a new search log and returns id', function () {
//   $dto = new LogSearchDTO(1, 'iphone', 'ar', 10, 'general', 0.9, 1, 'session123');

//   $id = $this->repository->logSearch($dto);

//   // التحقق من أن السجل تم إنشاؤه فعلياً في قاعدة البيانات
//   $this->assertDatabaseHas('user_search_logs', [
//     'id' => $id,
//     'keyword' => 'iphone',
//     'session_id' => 'session123'
//   ]);
// });

// test('logClick creates a new click log', function () {
//   // Mock for UserClickLog (Alias)
//   $mockClick = Mockery::mock('alias:App\Domains\Search\Models\UserClickLog');

//   $dto = new LogClickDTO(1, 101, 2, 1, 1, 5, 'session123');

//   $mockClick->shouldReceive('create')
//     ->once()
//     ->with(Mockery::type('array'))
//     ->andReturn(true);

//   $this->repository->logClick($dto);
// });

// test('getClickCountsByDataType returns aggregated data correctly', function () {
//     // 1. إنشاء بيانات تجريبية حقيقية
//     UserClickLog::factory()->create(['data_type_id' => 1, 'project_id' => 1, 'user_id' => 1]);
//     UserClickLog::factory()->count(4)->create(['data_type_id' => 1, 'project_id' => 1, 'user_id' => 1]); // المجموع 5
//     UserClickLog::factory()->count(10)->create(['data_type_id' => 2, 'project_id' => 1, 'user_id' => 1]); // المجموع 10

//     // 2. التنفيذ
//     $result = $this->repository->getClickCountsByDataType(1, 1, 30);

//     // 3. التحقق (لا تهتم بكيفية تنفيذ الـ SQL، اهتم بالنتيجة)
//     expect($result)->toBe([1 => 5, 2 => 10]);
// });

// test('getClickCountsByDataTypeForSession returns aggregated data correctly', function () {
//   $builder = Mockery::mock('Illuminate\Database\Query\Builder');

//   $builder->shouldReceive('select')->andReturnSelf();
//   $builder->shouldReceive('where')->times(3)->andReturnSelf();
//   $builder->shouldReceive('groupBy')->once()->andReturnSelf();

//   $data = collect([
//     (object)['data_type_id' => 3, 'click_count' => 2],
//   ]);

//   $builder->shouldReceive('get')->once()->andReturn($data);

//   DB::shouldReceive('table')->once()->with('user_click_logs')->andReturn($builder);

//   $result = $this->repository->getClickCountsByDataTypeForSession(1, 'session123', 30);

//   expect($result)->toBe([3 => 2]);
// });
