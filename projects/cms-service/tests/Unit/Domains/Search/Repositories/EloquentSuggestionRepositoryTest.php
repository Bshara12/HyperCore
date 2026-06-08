<?php

use App\Domains\Search\DTOs\SuggestionQueryDTO;
use App\Domains\Search\Repositories\Eloquent\EloquentSuggestionRepository;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->repository = new EloquentSuggestionRepository();

    // الحل: إخبار Mockery بأن يتعامل مع DB::raw() بشكل طبيعي
    // نقوم بإرجاع القيمة التي تمرر لـ raw كما هي لضمان سير الكود
    DB::shouldReceive('raw')->andReturnUsing(fn($value) => $value);
});

test('findByPrefix returns suggestions from database correctly', function () {
  $dto = new SuggestionQueryDTO('iph', 1, 'ar', 5, 1);

  // Mocking Query Builder chain
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');
  $builder->shouldReceive('select')->andReturnSelf();
  $builder->shouldReceive('where')->times(3)->andReturnSelf();
  $builder->shouldReceive('orderByDesc')->twice()->andReturnSelf();
  $builder->shouldReceive('limit')->once()->andReturnSelf();

  $results = collect([['keyword' => 'iphone 15', 'score' => 10.5]]);
  $builder->shouldReceive('get')->once()->andReturn($results);

  DB::shouldReceive('table')->once()->with('search_suggestions')->andReturn($builder);

  $response = $this->repository->findByPrefix($dto);

  expect($response)->toBe($results->toArray());
});

test('upsertFromSearch executes correct SQL statement', function () {
  // التحقق من استدعاء DB::statement مع الكويري الصحيح
  DB::shouldReceive('statement')
    ->once()
    ->with(Mockery::on(fn($sql) => str_contains($sql, 'INSERT INTO search_suggestions')), Mockery::type('array'))
    ->andReturn(true);

  $this->repository->upsertFromSearch(1, 'iphone', 'ar');
});

test('incrementClickCount executes update query correctly', function () {
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');

  $builder->shouldReceive('where')->times(3)->andReturnSelf();
  $builder->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
    return isset($data['click_count']) && isset($data['score']) && isset($data['updated_at']);
  }))->andReturn(1);

  DB::shouldReceive('table')->once()->with('search_suggestions')->andReturn($builder);

  $this->repository->incrementClickCount(1, 'iphone', 'ar');
});

test('buildFromSearchLogs performs bulk insertion and returns stats', function () {
  // Mock the bulk insert
  DB::shouldReceive('statement')
    ->once()
    ->with(Mockery::on(fn($sql) => str_contains($sql, 'INSERT INTO search_suggestions')), Mockery::any())
    ->andReturn(true);

  // Mock the counts for stats
  DB::shouldReceive('table')->with('search_suggestions')->once()->andReturnSelf();
  DB::shouldReceive('where')->once()->andReturnSelf();
  DB::shouldReceive('count')->once()->andReturn(10); // upserted

  DB::shouldReceive('table')->with('user_search_logs')->once()->andReturnSelf();
  DB::shouldReceive('where')->once()->andReturnSelf();
  DB::shouldReceive('count')->once()->andReturn(20); // processed

  $stats = $this->repository->buildFromSearchLogs(1);

  expect($stats)->toBe(['processed' => 20, 'upserted' => 10]);
});
