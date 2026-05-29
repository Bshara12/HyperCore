<?php

namespace Tests\Unit\Domains\Auth\Services;

use App\Domains\Auth\Service\AuthServiceClient;
use Illuminate\Support\Facades\Http;


beforeEach(function () {
  // ضبط الرابط الوهمي للإعدادات
  config(['services.auth_service.url' => 'https://auth.api']);
  $this->service = new AuthServiceClient();
});

test('it gets user from token correctly', function () {
  // 1. تعريف الرد الوهمي
  Http::fake([
    'https://auth.api/my-profile' => Http::response([
      'data' => [
        'name' => 'John Doe',
        'roles' => [
          ['permessions' => [['name' => 'edit-posts'], ['name' => 'view-posts']]],
          ['permessions' => [['name' => 'view-posts']]] // تكرار للتأكد من unique
        ]
      ]
    ], 200)
  ]);

  // 2. التنفيذ
  $user = $this->service->getUserFromToken('fake-token');

  // 3. التأكيد
  expect($user['name'])->toBe('John Doe')
    ->and($user['permissions'])->toHaveCount(2)
    ->and($user['permissions'])->toContain('edit-posts', 'view-posts');
});

test('it gets users by ids correctly', function () {
  // 1. تعريف الرد الوهمي
  Http::fake([
    'https://auth.api/users/by-ids' => Http::response([
      'data' => [['id' => 1, 'name' => 'User 1'], ['id' => 2, 'name' => 'User 2']]
    ], 200)
  ]);

  // 2. التنفيذ
  $users = $this->service->getUsersByIds([1, 2]);

  // 3. التأكيد
  expect($users)->toHaveCount(2)
    ->and($users[0]['name'])->toBe('User 1');

  // التأكد من أن الطلب تم إرساله بجسم صحيح
  Http::assertSent(function ($request) {
    return $request->hasHeader('Content-Type', 'application/json') &&
      $request['ids'] === [1, 2];
  });
});

test('it throws exception when get user profile fails', function () {
  // 1. تعريف رد وهمي بحالة خطأ (401 Unauthorized)
  Http::fake([
    'https://auth.api/my-profile' => Http::response('Unauthorized access', 401)
  ]);

  // 2. التأكد من أن التابع يرمي استثناءً (Exception)
  expect(fn() => $this->service->getUserFromToken('invalid-token'))
    ->toThrow(\Exception::class, 'Auth Service Error: Unauthorized access');
});

test('it throws exception when get users by ids fails', function () {
  // 1. تعريف رد وهمي بحالة خطأ (500 Internal Server Error)
  Http::fake([
    'https://auth.api/users/by-ids' => Http::response('Server Error', 500)
  ]);

  // 2. التأكد من أن التابع يرمي استثناءً
  expect(fn() => $this->service->getUsersByIds([1, 2]))
    ->toThrow(\Exception::class, 'Auth Service Error: Server Error');
});
