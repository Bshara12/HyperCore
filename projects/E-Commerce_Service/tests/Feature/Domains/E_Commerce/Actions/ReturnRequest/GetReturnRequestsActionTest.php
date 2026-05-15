<?php

namespace Tests\Feature\Domains\E_Commerce\Actions\ReturnRequest;

use App\Domains\E_Commerce\Actions\ReturnRequest\GetReturnRequestsAction;
use App\Domains\E_Commerce\DTOs\ReturnRequest\GetReturnRequestsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;
use Mockery;

beforeEach(function () {
  $this->repo = Mockery::mock(ReturnRequestRepositoryInterface::class);
  $this->action = new GetReturnRequestsAction($this->repo);
});

it('fetches return requests for a specific project', function () {
  // 1. تجهيز الـ DTO بـ project_id محدد
  $projectId = 5;
  $dto = new GetReturnRequestsDTO(
    project_id: $projectId
  );

  // 2. تجهيز بيانات وهمية متوقع رجوعها
  $mockedRequests = [
    ['id' => 1, 'order_id' => 101, 'status' => 'pending'],
    ['id' => 2, 'order_id' => 102, 'status' => 'approved'],
  ];

  // 3. محاكاة الـ Repository للتأكد أنه استلم الـ project_id الصحيح
  $this->repo->shouldReceive('getByProject')
    ->once()
    ->with($projectId)
    ->andReturn($mockedRequests);

  // 4. التنفيذ
  $result = $this->action->execute($dto);

  // 5. التحقق
  expect($result)->toBeArray()
    ->and($result)->toHaveCount(2)
    ->and($result[0]['id'])->toBe(1);
});

it('returns an empty array if no requests found for the project', function () {
  $dto = new GetReturnRequestsDTO(project_id: 99);

  $this->repo->shouldReceive('getByProject')
    ->once()
    ->with(99)
    ->andReturn([]);

  $result = $this->action->execute($dto);

  expect($result)->toBeArray()->toBeEmpty();
});
