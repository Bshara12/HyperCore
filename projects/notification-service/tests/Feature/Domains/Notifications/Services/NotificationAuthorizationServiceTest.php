<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Policies\NotificationPolicy;
use App\Domains\Notifications\Services\NotificationAuthorizationService;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
  // 1. إنشاء Mock لكلاس الـ Policy لأنه يحتوي على منطق الصلاحيات المعقد
  $this->policyMock = Mockery::mock(NotificationPolicy::class);

  // 2. 💡 بناء كائن حقيقي من الـ Actor وتمرير المعاملات الإجبارية (type و id) لتفادي الـ ArgumentCountError
  $this->actor = new NotificationActor(
    type: 'user',
    id: 1,
    permessions: []
  );

  // 3. استخدام كائن حقيقي فارغ للموديل باستخدام forceFill لتجنب مشاكل الـ final أو الـ magic methods
  $this->notification = (new Notification())->forceFill(['id' => 'notif-123']);

  // 4. حقن الـ Policy Mock داخل خدمة الصلاحيات
  $this->authService = new NotificationAuthorizationService($this->policyMock);
});

/**
 * --------------------------------------------------------------------------
 * Tests for ensureCanCreate
 * --------------------------------------------------------------------------
 */
it('allows creation when policy returns true for create', function () {
  $this->policyMock->shouldReceive('create')
    ->once()
    ->with($this->actor)
    ->andReturn(true);

  $this->authService->ensureCanCreate($this->actor);
});

it('throws exception when policy returns false for create', function () {
  $this->policyMock->shouldReceive('create')
    ->once()
    ->with($this->actor)
    ->andReturn(false);

  expect(fn() => $this->authService->ensureCanCreate($this->actor))
    ->toThrow(AuthorizationException::class, 'You are not authorized to create notifications.');
});

/**
 * --------------------------------------------------------------------------
 * Tests for ensureCanCreateSystem
 * --------------------------------------------------------------------------
 */
it('allows system creation when policy returns true for createSystem', function () {
  $this->policyMock->shouldReceive('createSystem')
    ->once()
    ->with($this->actor)
    ->andReturn(true);

  $this->authService->ensureCanCreateSystem($this->actor);
});

it('throws exception when policy returns false for createSystem', function () {
  $this->policyMock->shouldReceive('createSystem')
    ->once()
    ->with($this->actor)
    ->andReturn(false);

  expect(fn() => $this->authService->ensureCanCreateSystem($this->actor))
    ->toThrow(AuthorizationException::class, 'You are not authorized to create system notifications.');
});

/**
 * --------------------------------------------------------------------------
 * Tests for ensureCanViewAny
 * --------------------------------------------------------------------------
 */
it('allows viewing any when policy returns true for viewAny', function () {
  $projectId = 'project-123';

  $this->policyMock->shouldReceive('viewAny')
    ->once()
    ->with($this->actor, $projectId)
    ->andReturn(true);

  $this->authService->ensureCanViewAny($this->actor, $projectId);
});

it('throws exception when policy returns false for viewAny', function () {
  $this->policyMock->shouldReceive('viewAny')
    ->once()
    ->with($this->actor, null)
    ->andReturn(false);

  expect(fn() => $this->authService->ensureCanViewAny($this->actor, null))
    ->toThrow(AuthorizationException::class, 'You are not authorized to view notifications.');
});

/**
 * --------------------------------------------------------------------------
 * Tests for ensureCanView
 * --------------------------------------------------------------------------
 */
it('allows viewing a notification when policy returns true for view', function () {
  $this->policyMock->shouldReceive('view')
    ->once()
    ->with($this->actor, $this->notification)
    ->andReturn(true);

  $this->authService->ensureCanView($this->actor, $this->notification);
});

it('throws exception when policy returns false for view', function () {
  $this->policyMock->shouldReceive('view')
    ->once()
    ->with($this->actor, $this->notification)
    ->andReturn(false);

  expect(fn() => $this->authService->ensureCanView($this->actor, $this->notification))
    ->toThrow(AuthorizationException::class, 'You are not authorized to view this notification.');
});

/**
 * --------------------------------------------------------------------------
 * Tests for ensureCanMarkAsRead
 * --------------------------------------------------------------------------
 */
it('allows marking as read when policy returns true for markAsRead', function () {
  $this->policyMock->shouldReceive('markAsRead')
    ->once()
    ->with($this->actor, $this->notification)
    ->andReturn(true);

  $this->authService->ensureCanMarkAsRead($this->actor, $this->notification);
});

it('throws exception when policy returns false for markAsRead', function () {
  $this->policyMock->shouldReceive('markAsRead')
    ->once()
    ->with($this->actor, $this->notification)
    ->andReturn(false);

  expect(fn() => $this->authService->ensureCanMarkAsRead($this->actor, $this->notification))
    ->toThrow(AuthorizationException::class, 'You are not authorized to mark this notification as read.');
});

/**
 * --------------------------------------------------------------------------
 * Tests for ensureCanMarkAllAsRead
 * --------------------------------------------------------------------------
 */
it('allows marking all as read when policy returns true for markAllAsRead', function () {
  $projectId = 'project-456';

  $this->policyMock->shouldReceive('markAllAsRead')
    ->once()
    ->with($this->actor, $projectId)
    ->andReturn(true);

  $this->authService->ensureCanMarkAllAsRead($this->actor, $projectId);
});

it('throws exception when policy returns false for markAllAsRead', function () {
  $this->policyMock->shouldReceive('markAllAsRead')
    ->once()
    ->with($this->actor, null)
    ->andReturn(false);

  expect(fn() => $this->authService->ensureCanMarkAllAsRead($this->actor, null))
    ->toThrow(AuthorizationException::class, 'You are not authorized to mark notifications as read.');
});
