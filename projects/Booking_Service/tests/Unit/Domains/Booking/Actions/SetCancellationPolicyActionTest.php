<?php

namespace Tests\Unit\Domains\Booking\Actions;

use App\Domains\Booking\Actions\SetCancellationPolicyAction;
use App\Domains\Booking\DTOs\CancellationPolicyDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

test('it sets cancellation policies and invalidates related cache', function () {
  // 1. تجهيز البيانات
  $resourceId = 15;
  $projectId = 2;
  $resource = (new Resource())->forceFill([
    'id' => $resourceId,
    'project_id' => $projectId
  ]);

  // ملاحظة: تأكد أن هذه المفاتيح تطابق ما يتوقعه CancellationPolicyDTO::fromArray لديك
  $policiesData = [
    [
      'hours_before' => 24,
      'refund_percentage' => 100,
      'is_active' => true
    ],
    [
      'hours_before' => 12,
      'refund_percentage' => 50,
      'is_active' => true
    ]
  ];

  // 2. ملء الكاش للتأكد من حذفه
  $singleKey = CacheKeys::resource($resourceId);
  $listKey = CacheKeys::resources($projectId);
  Cache::put($singleKey, 'old_data');
  Cache::put($listKey, 'old_data');

  // 3. محاكاة المستودع
  $repository = Mockery::mock(ResourceRepositoryInterface::class);
  $repository->shouldReceive('setPolicies')
    ->once()
    ->with($resource, Mockery::on(function ($dtos) {
      // نتحقق أن المصفوفة تحتوي على كائنات DTO الصحيحة
      return is_array($dtos) && $dtos[0] instanceof CancellationPolicyDTO;
    }));

  $action = new SetCancellationPolicyAction($repository);

  // 4. التنفيذ
  $action->execute($resource, $policiesData);

  // 5. التحققات
  expect(Cache::has($singleKey))->toBeFalse('Individual resource cache was not cleared');
  expect(Cache::has($listKey))->toBeFalse('Project resources list cache was not cleared');
});
