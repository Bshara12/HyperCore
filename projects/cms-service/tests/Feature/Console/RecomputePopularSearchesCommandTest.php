<?php

use App\Console\Commands\RecomputePopularSearchesCommand;
use App\Domains\Search\Services\PopularSearchService;

test('it cancels recomputation when confirmation is declined', function () {
  $this->artisan('search:recompute-popular')
    ->expectsConfirmation('Recompute popular searches for ALL projects?', 'no')
    ->expectsOutput('Cancelled.')
    ->assertExitCode(0);
});

test('it recomputes for all projects when no project id is provided', function () {
  $results = [
    ['project_id' => 1, 'language' => 'en', 'stats' => ['processed' => 10, 'upserted' => 5, 'duration_ms' => 100]]
  ];

  $serviceMock = Mockery::mock(PopularSearchService::class);
  $serviceMock->shouldReceive('recompute')->once()->with(null)->andReturn($results);
  $this->app->instance(PopularSearchService::class, $serviceMock);

  $this->artisan('search:recompute-popular --force')
    ->expectsTable(['Project', 'Language', 'Processed', 'Upserted', 'Duration'], [
      [1, 'en', 10, 5, '100ms']
    ])
    ->assertExitCode(0);
});

test('it recomputes for specific project', function () {
  $results = [
    ['project_id' => 10, 'language' => 'ar', 'stats' => ['processed' => 20, 'upserted' => 10, 'duration_ms' => 200]]
  ];

  $serviceMock = Mockery::mock(PopularSearchService::class);
  $serviceMock->shouldReceive('recompute')->once()->with(10)->andReturn($results);
  $this->app->instance(PopularSearchService::class, $serviceMock);

  $this->artisan('search:recompute-popular --project=10 --force')
    ->expectsTable(['Project', 'Language', 'Processed', 'Upserted', 'Duration'], [
      [10, 'ar', 20, 10, '200ms']
    ])
    ->assertExitCode(0);
});
