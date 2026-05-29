<?php

use App\Domains\Subscription\DTOs\ContentAccess\CreateContentAccessDTO;
use App\Domains\Subscription\Requests\ContentAccess\CreateContentAccessRequest;

test('it initializes with all properties correctly', function () {
  $dto = new CreateContentAccessDTO(
    projectId: 1,
    contentId: 100,
    requiresSubscription: true,
    features: ['premium', 'ad-free'],
    metadata: ['expires_at' => '2026-12-31']
  );

  expect($dto->projectId)->toBe(1)
    ->and($dto->contentId)->toBe(100)
    ->and($dto->requiresSubscription)->toBeTrue()
    ->and($dto->features)->toBe(['premium', 'ad-free'])
    ->and($dto->metadata)->toBe(['expires_at' => '2026-12-31']);
});

test('it creates a DTO from CreateContentAccessRequest correctly', function () {
  // 1. إنشاء Mock للـ Request
  $request = Mockery::mock(CreateContentAccessRequest::class);

  // تعيين الخصائص التي يتم الوصول إليها مباشرة
  $request->project_id = 5;
  $request->content_id = '200'; // سيتم تحويله إلى int

  // 2. محاكاة ميثود الـ input والـ boolean
  $request->shouldReceive('boolean')
    ->once()
    ->with('requires_subscription')
    ->andReturn(true);

  $request->shouldReceive('input')
    ->once()
    ->with('features', [])
    ->andReturn(['basic']);

  $request->shouldReceive('input')
    ->once()
    ->with('metadata')
    ->andReturn(['foo' => 'bar']);

  // 3. استدعاء الـ Factory Method
  $dto = CreateContentAccessDTO::fromRequest($request);

  // 4. التحقق
  expect($dto->projectId)->toBe(5)
    ->and($dto->contentId)->toBe(200) // تأكدنا من التحويل لـ int
    ->and($dto->requiresSubscription)->toBeTrue()
    ->and($dto->features)->toBe(['basic'])
    ->and($dto->metadata)->toBe(['foo' => 'bar']);
});

test('it handles default values when fields are empty in request', function () {
  $request = Mockery::mock(CreateContentAccessRequest::class);
  $request->project_id = null;
  $request->content_id = '300';

  $request->shouldReceive('boolean')->andReturn(false);

  // محاكاة features فارغة
  $request->shouldReceive('input')->with('features', [])->andReturn([]);
  $request->shouldReceive('input')->with('metadata')->andReturn(null);

  $dto = CreateContentAccessDTO::fromRequest($request);

  expect($dto->projectId)->toBeNull()
    ->and($dto->features)->toBeEmpty()
    ->and($dto->requiresSubscription)->toBeFalse();
});
