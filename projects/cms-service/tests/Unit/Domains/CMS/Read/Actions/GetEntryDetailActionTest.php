<?php

namespace Tests\Unit\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Actions\GetEntryDetailAction;
use App\Domains\CMS\Read\DTOs\EntryDetailDTO;
use App\Domains\CMS\Read\DTOs\GetEntryDetailDTO;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Support\LanguageResolver;
use App\Domains\Subscription\Services\AuthorizationEngineService;
use App\Domains\Subscription\Services\ContentAuthorizationService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
  $this->repo = mock(EntryReadRepository::class);
  $this->langResolver = mock(LanguageResolver::class);
  $this->authEngine = mock(AuthorizationEngineService::class);
  $this->contentAuth = mock(ContentAuthorizationService::class);

  $this->action = new GetEntryDetailAction(
    $this->repo,
    $this->langResolver,
    $this->authEngine,
    $this->contentAuth
  );
});

test('it returns null if entry not found', function () {
  $dto = new GetEntryDetailDTO(entryId: 1, userId: 100, language: 'en');

  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');

  // محاكاة عدم وجود بيانات (Cache::remember سيعيد null)
  Cache::shouldReceive('remember')->once()->andReturn(null);

  $result = $this->action->execute($dto);

  expect($result)->toBeNull();
});

test('it retrieves entry details and authorizes access', function () {
  $dto = new GetEntryDetailDTO(entryId: 1, userId: 100, language: 'en');

  $mockData = [
    'id' => 1,
    'slug' => 'test-entry',
    'status' => 'published',
    'values' => ['title' => 'Test'],
    'seo' => ['title' => 'SEO Title'],
    'project_id' => 5,
    'data_type_slug' => 'article'
  ];

  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');

  // خدعة الاختبار: تنفيذ الـ Closure فوراً عند استدعاء remember
  Cache::shouldReceive('remember')
    ->once()
    ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

  $this->repo->shouldReceive('findPublishedWithValues')
    ->once()
    ->andReturn($mockData);

  // التحقق من أن Authorization تم استدعاؤها
  $this->authEngine->shouldReceive('authorize')->once();
  $this->contentAuth->shouldReceive('authorize')->once();

  $result = $this->action->execute($dto);

  // التأكيد
  expect($result)->toBeInstanceOf(EntryDetailDTO::class)
    ->and($result->id)->toBe(1)
    ->and($result->slug)->toBe('test-entry');
});
