<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\PlanController;
use App\Domains\Subscription\Services\PlanService;
use App\Domains\Subscription\Requests\Plan\CreatePlanRequest;
use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class PlanControllerTest extends TestCase
{
  private PlanController $controller;
  private $planServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    // محاكاة خدمة الخطط
    $this->planServiceMock = Mockery::mock(PlanService::class);

    // حقن الخدمة في الـ Controller
    $this->controller = new PlanController($this->planServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_stores_a_new_plan_successfully()
  {
    // 1. محاكاة الـ Request
    $request = Mockery::mock(CreatePlanRequest::class);
    $request->shouldIgnoreMissing();

    $request->project_id = 1;
    $request->name = 'Gold Plan';
    $request->slug = 'gold-plan';
    $request->description = 'A premium plan';
    $request->price = 99.99;
    $request->currency = 'USD';
    $request->duration_days = 30;
    $request->features = ['feature1', 'feature2'];
    $request->metadata = ['tier' => 'vip'];

    $request->shouldReceive('boolean')
      ->with('is_active', true)
      ->andReturn(true);

    // 2. إنشاء كائن حقيقي من الموديل (بدون Mockery)
    // هذا يحل مشكلة الـ Type Hint ويتجنب الاتصال بقاعدة البيانات
    $plan = new \App\Models\SubscriptionPlan();
    $plan->id = 10;
    $plan->name = 'Gold Plan';
    $plan->price = 99.99;

    // 3. توقع استدعاء الخدمة وإرجاع الكائن الحقيقي
    $this->planServiceMock->shouldReceive('create')
      ->once()
      ->with(Mockery::type(CreatePlanDTO::class))
      ->andReturn($plan);

    // 4. التنفيذ
    $response = $this->controller->store($request);

    // 5. التأكيد
    $this->assertEquals(201, $response->getStatusCode());

    $responseData = $response->getData(true);
    // التأكد من أن البيانات تطابق الموديل الذي أنشأناه
    $this->assertEquals('Gold Plan', $responseData['data']['name']);
    $this->assertEquals(10, $responseData['data']['id']);
  }
}
