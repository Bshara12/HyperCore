<?php

use App\Domains\Subscription\Repositories\Eloquent\EloquentSubscriptionFeatureRuleRepository;
use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Models\Project;
use App\Models\SubscriptionFeatureRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentSubscriptionFeatureRuleRepository();
  $this->project = Project::factory()->create();
});

test('create persists rule using DTO', function () {
    $dto = new CreateFeatureRuleDTO(
        projectId: $this->project->id,
        eventKey: 'user_login',
        featureKey: 'premium_dashboard',
        action: 'check', // تم تعديلها لتكون قيمة مقبولة (check)
        resetType: 'monthly',
        isActive: true,
        metadata: ['role' => 'admin']
    );

    $rule = $this->repository->create($dto);

    $this->assertDatabaseHas('subscription_feature_rules', [
        'id' => $rule->id,
        'feature_key' => 'premium_dashboard',
        'action' => 'check',
    ]);
});

test('findActiveRulesByEvent returns only active rules', function () {
    // السجل الأول
    SubscriptionFeatureRule::factory()->create([
        'project_id' => $this->project->id,
        'event_key' => 'click_button',
        'feature_key' => 'feature_1', // مفتاح مختلف
        'is_active' => true
    ]);

    // السجل الثاني (مختلف في feature_key ليتجاوز الـ Unique Constraint)
    SubscriptionFeatureRule::factory()->create([
        'project_id' => $this->project->id,
        'event_key' => 'click_button',
        'feature_key' => 'feature_2', // مفتاح مختلف
        'is_active' => false
    ]);

    $results = $this->repository->findActiveRulesByEvent($this->project->id, 'click_button');

    expect($results)->toHaveCount(1)
        ->and($results->first()->is_active)->toBeTrue();
});