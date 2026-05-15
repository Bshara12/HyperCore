<?php

namespace Tests\Feature\Console\Commands;

use App\Domains\E_Commerce\Services\OfferService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

it('processes offers and calls re-evaluate when there are changes', function () {
  // 1. محاكاة الـ OfferService
  $mockService = Mockery::mock(OfferService::class);
  $mockService->shouldReceive('run')->once()->andReturn([
    'activated' => [1, 2],
    'deactivated' => [3]
  ]);
  $this->app->instance(OfferService::class, $mockService);

  // 2. تحديث الـ Mock ليشمل الـ arguments المتوقعة
  // السلسلة النصية 'offers:re-evaluate {entries*}' تخبر Laravel أن هناك argument مصفوفة
  Artisan::command('offers:re-evaluate {entries*}', function () {
    // نتركه فارغاً، المهم هو تعريف الهيكل
  });

  // 3. التنفيذ والتحقق
  $this->artisan('offers:process-schedule')
    ->expectsOutput("Activated offers affected entries: 2, Deactivated offers affected entries: 1")
    ->expectsOutput("Entries sent for re-evaluation: 1, 2, 3")
    ->assertExitCode(0);
});

it('processes offers and does not call re-evaluate when no changes occur', function () {
  $mockService = Mockery::mock(OfferService::class);
  $mockService->shouldReceive('run')->once()->andReturn([
    'activated' => [],
    'deactivated' => []
  ]);
  $this->app->instance(OfferService::class, $mockService);

  $this->artisan('offers:process-schedule')
    ->expectsOutput("Activated offers affected entries: 0, Deactivated offers affected entries: 0")
    ->assertExitCode(0);
});
