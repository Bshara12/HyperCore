<?php

namespace Tests\Feature\Services;

use App\Repositories\OperationRepositoryInteface;
use App\Services\OperationServices;
use Illuminate\Support\Collection;

beforeEach(function () {
  // محاكاة الـ Repository الوحيد الذي تعتمد عليه الخدمة
  $this->operationRepositoryMock = mock(OperationRepositoryInteface::class);

  // حقن الـ Mock داخل الخدمة لتهيئة بيئة الاختبار
  $this->operationServices = new OperationServices($this->operationRepositoryMock);
});

// =========================================================================
// 1. اختبار دالة: getUsersService
// =========================================================================

test('getUsersService returns all users from repository', function () {
  $expectedUsers = collect([
    ['id' => 1, 'name' => 'Ali'],
    ['id' => 2, 'name' => 'Omar']
  ]);

  $this->operationRepositoryMock->shouldReceive('getAllUsers')
    ->once()
    ->andReturn($expectedUsers);

  $result = $this->operationServices->getUsersService();

  expect($result)->toBe($expectedUsers);
});

// =========================================================================
// 2. اختبار دالة: assginRoleService
// =========================================================================

test('assginRoleService passes correct user and role data to repository', function () {
  $data = [
    'user_id' => 10,
    'role_id' => 2
  ];

  $this->operationRepositoryMock->shouldReceive('assginRoleToUser')
    ->once()
    ->with(10, 2)
    ->andReturn(true);

  $result = $this->operationServices->assginRoleService($data);

  expect($result)->toBeTrue();
});

// =========================================================================
// 3. اختبار دالة: removeRoleService
// =========================================================================

test('removeRoleService calls repository with correct user id', function () {
  $data = [
    'user_id' => 15
  ];

  $this->operationRepositoryMock->shouldReceive('removeRoleFromUser')
    ->once()
    ->with(15)
    ->andReturn(true);

  $result = $this->operationServices->removeRoleService($data);

  expect($result)->toBeTrue();
});

// =========================================================================
// 4. اختبار دالة: addPermessionService
// =========================================================================

test('addPermessionService dispatches permission payload to repository', function () {
  $data = [
    'permession' => 'edit-articles'
  ];

  $this->operationRepositoryMock->shouldReceive('addPermession')
    ->once()
    ->with('edit-articles')
    ->andReturn(true);

  $result = $this->operationServices->addPermessionService($data);

  expect($result)->toBeTrue();
});

// =========================================================================
// 5. اختبار دالة: assginPermToRoleService
// =========================================================================

test('assginPermToRoleService links permission and role correctly via repository', function () {
  $data = [
    'permession_id' => 5,
    'role_id'       => 1
  ];

  $this->operationRepositoryMock->shouldReceive('assginPermToRole')
    ->once()
    ->with(5, 1)
    ->andReturn(true);

  $result = $this->operationServices->assginPermToRoleService($data);

  expect($result)->toBeTrue();
});

// =========================================================================
// 6. اختبار دالة: removePermToRoleService
// =========================================================================

test('removePermToRoleService detaches permission from role via repository', function () {
  $data = [
    'permession_id' => 5,
    'role_id'       => 1
  ];

  $this->operationRepositoryMock->shouldReceive('removePermFromRole')
    ->once()
    ->with(5, 1)
    ->andReturn(true);

  $result = $this->operationServices->removePermToRoleService($data);

  expect($result)->toBeTrue();
});

// =========================================================================
// 7. اختبار دالة: getAllRolesService
// =========================================================================

test('getAllRolesService fetches all available roles', function () {
  $expectedRoles = collect([
    ['id' => 1, 'name' => 'admin'],
    ['id' => 2, 'name' => 'editor']
  ]);

  $this->operationRepositoryMock->shouldReceive('getAllRoles')
    ->once()
    ->andReturn($expectedRoles);

  $result = $this->operationServices->getAllRolesService();

  expect($result)->toBe($expectedRoles);
});

// =========================================================================
// 8. اختبار دالة: getAllPermissionsService
// =========================================================================

test('getAllPermissionsService fetches all available permissions', function () {
  $expectedPermissions = collect([
    ['id' => 1, 'name' => 'create-user'],
    ['id' => 2, 'name' => 'delete-user']
  ]);

  $this->operationRepositoryMock->shouldReceive('getAllPermissions')
    ->once()
    ->andReturn($expectedPermissions);

  $result = $this->operationServices->getAllPermissionsService();

  expect($result)->toBe($expectedPermissions);
});
