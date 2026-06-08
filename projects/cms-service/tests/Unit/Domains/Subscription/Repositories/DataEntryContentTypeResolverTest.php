<?php

use App\Domains\Subscription\Repositories\Eloquent\DataEntryContentTypeResolver;
use App\Exceptions\ContentEntryNotFoundException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
  $this->resolver = new DataEntryContentTypeResolver();
});

test('resolve returns slug when content entry exists', function () {
  // بناء Mock للـ Query Builder
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');

  $builder->shouldReceive('join')
    ->once()
    ->with('data_types', 'data_entries.data_type_id', '=', 'data_types.id')
    ->andReturnSelf();

  $builder->shouldReceive('where')
    ->once()
    ->with('data_entries.id', 123)
    ->andReturnSelf();

  $builder->shouldReceive('whereNull')
    ->once()
    ->with('data_entries.deleted_at')
    ->andReturnSelf();

  $builder->shouldReceive('value')
    ->once()
    ->with('data_types.slug')
    ->andReturn('article');

  // ربط الـ table بالـ builder
  DB::shouldReceive('table')
    ->once()
    ->with('data_entries')
    ->andReturn($builder);

  $slug = $this->resolver->resolve(123);

  expect($slug)->toBe('article');
});

test('resolve throws ContentEntryNotFoundException when entry does not exist', function () {
  // 1. إنشاء Mock للـ Builder
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');

  // 2. يجب إرجاع Self لكل خطوة في السلسلة لضمان عدم انكسار الـ Chain
  $builder->shouldReceive('join')->andReturnSelf();
  $builder->shouldReceive('where')->andReturnSelf();
  $builder->shouldReceive('whereNull')->andReturnSelf();

  // 3. هذا هو المكان الذي يحدث فيه "عدم الإيجاد"، لذا نعيد null
  $builder->shouldReceive('value')->once()->andReturn(null);

  // 4. ربط الـ table بالـ builder
  DB::shouldReceive('table')->once()->with('data_entries')->andReturn($builder);

  // 5. التنفيذ
  $this->resolver->resolve(999);
})->throws(ContentEntryNotFoundException::class);
