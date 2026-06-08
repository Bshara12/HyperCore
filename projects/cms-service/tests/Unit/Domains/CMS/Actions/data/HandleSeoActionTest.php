<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\HandleSeoAction;
use App\Domains\CMS\Repositories\Interface\SeoEntryRepository;
use App\Domains\CMS\Services\SeoGeneratorService;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
use Mockery;

// إعداد مشترك للـ CircuitBreaker
beforeEach(function () {
  $circuitMock = Mockery::mock(CircuitBreakerService::class);
  $circuitMock->shouldReceive('canProceed')->with('dataEntry.handleSeo')->andReturn(true);
  $circuitMock->shouldIgnoreMissing();
  app()->instance(CircuitBreakerService::class, $circuitMock);
});

test('it uses provided seo data when available', function () {
  $seoRepo = Mockery::mock(SeoEntryRepository::class);
  $seoGenerator = Mockery::mock(SeoGeneratorService::class);

  $entryId = 1;
  $seoData = ['title' => 'Custom Title'];
  $values = ['content' => 'Sample content'];

  // التوقعات
  $seoRepo->shouldReceive('deleteForEntry')->once()->with($entryId);
  $seoRepo->shouldReceive('insertForEntry')->once()->with($entryId, $seoData);

  // يجب ألا يتم استدعاء المولد لأننا قدمنا بيانات مخصصة
  $seoGenerator->shouldNotReceive('generate');

  Event::fake();

  $action = new HandleSeoAction($seoRepo, $seoGenerator);
  $action->execute($entryId, $seoData, $values);

  Event::assertDispatched(SystemLogEvent::class);
});

test('it generates seo automatically when seo data is null', function () {
  $seoRepo = Mockery::mock(SeoEntryRepository::class);
  $seoGenerator = Mockery::mock(SeoGeneratorService::class);

  $entryId = 1;
  $seoData = null; // لا يوجد بيانات مخصصة
  $values = ['content' => 'Sample content'];
  $generatedSeo = ['title' => 'Generated Title'];

  // التوقعات
  $seoRepo->shouldReceive('deleteForEntry')->once()->with($entryId);

  // يجب استدعاء المولد وتوليد بيانات
  $seoGenerator->shouldReceive('generate')
    ->once()
    ->with($values)
    ->andReturn($generatedSeo);

  // التأكد من إدخال البيانات المولدة
  $seoRepo->shouldReceive('insertForEntry')->once()->with($entryId, $generatedSeo);

  Event::fake();

  $action = new HandleSeoAction($seoRepo, $seoGenerator);
  $action->execute($entryId, $seoData, $values);

  Event::assertDispatched(SystemLogEvent::class);
});
