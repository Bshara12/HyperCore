<?php

use App\Domains\Subscription\Repositories\Eloquent\EloquentSubscriptionAccessRuleRepository;
use App\Models\SubscriptionAccessRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentSubscriptionAccessRuleRepository();

  // إنشاء مشروع حقيقي أولاً ليتم ربط القاعدة به
  $this->project = \App\Models\Project::factory()->create();
});

test('findActiveRule returns the rule when project and event key match and it is active', function () {
  $rule = SubscriptionAccessRule::factory()->create([
    'project_id' => $this->project->id, // استخدم الـ ID الخاص بالمشروع
    'event_key' => 'login_event',
    'is_active' => true,
  ]);

  // تأكد هنا أيضاً من استخدام $this->project->id
  $result = $this->repository->findActiveRule($this->project->id, 'login_event');

  expect($result)->not->toBeNull()
    ->and($result->id)->toBe($rule->id);
});

test('findActiveRule returns null when the rule is inactive', function () {
  SubscriptionAccessRule::factory()->create([
    'project_id' => $this->project->id, // استخدم الـ ID الخاص بالمشروع الذي أنشأناه    'event_key' => 'login_event',
    'is_active' => false,
  ]);

  $result = $this->repository->findActiveRule(1, 'login_event');

  expect($result)->toBeNull();
});

test('findActiveRule returns null when criteria do not match', function () {
  SubscriptionAccessRule::factory()->create([
    'project_id' => $this->project->id, // استخدم الـ ID الخاص بالمشروع الذي أنشأناه    'event_key' => 'login_event',
    'is_active' => true,
  ]);

  // البحث بـ project_id مختلف
  $result = $this->repository->findActiveRule(2, 'login_event');
  expect($result)->toBeNull();

  // البحث بـ event_key مختلف
  $result = $this->repository->findActiveRule(1, 'wrong_event');
  expect($result)->toBeNull();
});

test('findActiveRuleByEvent returns the rule when active', function () {
  $rule = SubscriptionAccessRule::factory()->create([
    'project_id' => $this->project->id, // استخدم المتغير
    'event_key' => 'signup_event',
    'is_active' => true,
  ]);

  // هنا التصحيح: استخدم $this->project->id بدلاً من 1
  $result = $this->repository->findActiveRuleByEvent($this->project->id, 'signup_event');

  expect($result)->not->toBeNull()
    ->and($result->id)->toBe($rule->id);
});
