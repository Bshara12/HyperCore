<?php

use App\Domains\Subscription\DTOs\ContentAccess\UpdateContentAccessMetadataDTO;
use App\Domains\Subscription\Requests\ContentAccess\UpdateContentAccessMetadataRequest;
use App\Models\ContentAccessMetadata;

test('it initializes with all properties correctly', function () {
  $metadataModel = Mockery::mock(ContentAccessMetadata::class);

  $dto = new UpdateContentAccessMetadataDTO(
    projectId: 1,
    contentId: 100,
    requiresSubscription: true,
    features: ['premium'],
    isActive: true,
    metadata: ['key' => 'value'],
    contentAccessMetadata: $metadataModel
  );

  expect($dto->projectId)->toBe(1)
    ->and($dto->contentId)->toBe(100)
    ->and($dto->requiresSubscription)->toBeTrue()
    ->and($dto->contentAccessMetadata)->toBe($metadataModel);
});

test('it creates a DTO from request with all fields present', function () {
  $request = Mockery::mock(UpdateContentAccessMetadataRequest::class);
  $metadataModel = Mockery::mock(ContentAccessMetadata::class);

  // تجهيز بيانات الـ Request
  $request->project_id = 5;
  $request->content_id = '500'; // سيتم تحويله لـ int

  // محاكاة الميثودز
  $request->shouldReceive('input')->with('content_id')->andReturn('500');
  $request->shouldReceive('boolean')->with('requires_subscription')->andReturn(true);
  $request->shouldReceive('input')->with('features', [])->andReturn(['pro']);
  $request->shouldReceive('boolean')->with('is_active', true)->andReturn(true);
  $request->shouldReceive('input')->with('metadata')->andReturn(['theme' => 'dark']);

  $dto = UpdateContentAccessMetadataDTO::fromRequest($request, $metadataModel);

  expect($dto->contentId)->toBe(500)
    ->and($dto->isActive)->toBeTrue()
    ->and($dto->features)->toBe(['pro']);
});

test('it handles null content_id correctly from request', function () {
  $request = Mockery::mock(UpdateContentAccessMetadataRequest::class);
  $metadataModel = Mockery::mock(ContentAccessMetadata::class);

  // حالة إرسال null للـ content_id
  $request->project_id = null;
  $request->shouldReceive('input')->with('content_id')->andReturn(null);

  // إكمال محاكاة بقية الحقول
  $request->shouldReceive('boolean')->andReturn(false);
  $request->shouldReceive('input')->with('features', [])->andReturn([]);
  $request->shouldReceive('input')->with('metadata')->andReturn(null);

  $dto = UpdateContentAccessMetadataDTO::fromRequest($request, $metadataModel);

  // التأكد أن الـ contentId أصبح null كما هو مطلوب في الكلاس
  expect($dto->contentId)->toBeNull();
});
