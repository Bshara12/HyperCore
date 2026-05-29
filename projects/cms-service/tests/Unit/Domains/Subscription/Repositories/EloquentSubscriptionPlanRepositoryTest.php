<?php

use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Domains\Subscription\Repositories\Eloquent\EloquentSubscriptionPlanRepository;
use App\Models\Project;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentSubscriptionPlanRepository();
  $this->project = Project::factory()->create();
});

test('createWithFeatures persists plan and features in transaction', function () {
  $dto = new CreatePlanDTO(
    projectId: $this->project->id,
    name: 'Pro Plan',
    slug: 'pro-plan',
    description: 'Best plan',
    price: 99.99,
    currency: 'USD',
    durationDays: 30,
    isActive: true,
    features: [
      ['feature_key' => 'storage', 'feature_type' => 'limit', 'feature_value' => ['limit' => 100]],
      ['feature_key' => 'api', 'feature_type' => 'access', 'feature_value' => ['enabled' => true]],
    ],
    metadata: ['tags' => 'premium']
  );

  $plan = $this->repository->createWithFeatures($dto);

  // التأكد من حفظ الخطة
  $this->assertDatabaseHas('subscription_plans', [
    'id' => $plan->id,
    'name' => 'Pro Plan',
  ]);

  // التأكد من حفظ الـ Features التابعة لها
  $this->assertDatabaseHas('subscription_features', [
    'plan_id' => $plan->id,
    'feature_key' => 'storage',
  ]);

  // التأكد من أن العلاقة تم تحميلها (Eager loading)
  expect($plan->features)->toHaveCount(2);
});

test('slugExists returns true if slug exists for project', function () {
  SubscriptionPlan::factory()->create([
    'project_id' => $this->project->id,
    'slug' => 'existing-slug'
  ]);

  $exists = $this->repository->slugExists($this->project->id, 'existing-slug');
  $notExists = $this->repository->slugExists($this->project->id, 'non-existent-slug');

  expect($exists)->toBeTrue()
    ->and($notExists)->toBeFalse();
});
