<?php

use App\Console\Commands\BuildSuggestionsCommand;
use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use Illuminate\Support\Facades\DB;

test('it handles empty logs and exits successfully', function () {
  // 1. Mock لـ DB ليعيد مصفوفة فارغة
  DB::shouldReceive('table')->with('user_search_logs')->andReturnSelf();
  DB::shouldReceive('distinct')->andReturnSelf();
  DB::shouldReceive('pluck')->andReturn(collect([]));

  $this->artisan('search:build-suggestions')
    ->expectsOutput('No projects found in user_search_logs.')
    ->assertExitCode(0);
});

test('it processes specific projects passed via option', function () {
  // 1. Mock للـ Repository
  $repositoryMock = Mockery::mock(SuggestionRepositoryInterface::class);
  $repositoryMock->shouldReceive('buildFromSearchLogs')
    ->with(123)
    ->once()
    ->andReturn(['processed' => 10, 'upserted' => 5]);

  $this->app->instance(SuggestionRepositoryInterface::class, $repositoryMock);

  // 2. التنفيذ
  $this->artisan('search:build-suggestions --project=123')
    ->expectsOutput('Building suggestions for 1 project(s)...')
    ->expectsOutput('Processing project 123...')
    ->expectsTable(['Metric', 'Value'], [
      ['Logs Processed', 10],
      ['Suggestions Upserted', 5],
    ])
    ->expectsOutput('✓ Done.')
    ->assertExitCode(0);
});

test('it processes all projects found in logs if no option provided', function () {
  // 1. Mock للـ DB
  DB::shouldReceive('table')->with('user_search_logs')->andReturnSelf();
  DB::shouldReceive('distinct')->andReturnSelf();
  DB::shouldReceive('pluck')->andReturn(collect([1, 2]));

  // 2. Mock للـ Repository
  $repositoryMock = Mockery::mock(SuggestionRepositoryInterface::class);
  $repositoryMock->shouldReceive('buildFromSearchLogs')->twice()->andReturn(['processed' => 0, 'upserted' => 0]);
  $this->app->instance(SuggestionRepositoryInterface::class, $repositoryMock);

  $this->artisan('search:build-suggestions')
    ->expectsOutput('Building suggestions for 2 project(s)...')
    ->assertExitCode(0);
});
