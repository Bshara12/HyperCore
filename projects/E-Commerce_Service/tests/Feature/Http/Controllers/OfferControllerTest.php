<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\OfferService;
use App\Services\CMS\CMSApiClient;
use App\Http\Controllers\OfferController;
use App\Domains\E_Commerce\Requests\CreateOfferRequest;
use App\Domains\E_Commerce\Requests\UpdateOfferRequest;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class OfferControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $offerServiceMock;
  private MockInterface $cmsApiMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->offerServiceMock = $this->mock(OfferService::class);
    $this->cmsApiMock = $this->mock(CMSApiClient::class);
  }

  #[Test]
  public function it_can_store_offer_successfully()
  {
    // 1. تجهيز البيانات (Payload)
    $payload = [
      "project_id" => 1,
      "name" => "Test Offer",
      "type" => "dynamic",
      "data_type_id" => 1,
      "conditions_logic" => "and",
      "conditions" => [
        ["field" => "price", "operator" => ">=", "value" => 250]
      ],
      "description" => "عرضي الخاص",
      "is_code_offer" => true,
      "offer_duration" => 4,
      "benefit_type" => "fixed_amount",
      "benefit_config" => ["fixed_amount" => 100],
      "is_active" => true
    ];

    // 2. توقع استدعاء الخدمة
    $this->offerServiceMock
      ->shouldReceive('create')
      ->once()
      ->andReturn(null); // تابع create لا يعيد بيانات حسب الكود

    // 3. بناء الـ Request
    $request = CreateOfferRequest::create('/api/ecommerce/offers', 'POST', $payload);
    $this->app->instance(CreateOfferRequest::class, $request);

    // 4. تنفيذ التابع
    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->store($request);

    // 5. التحقق
    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(201)
      ->assertJson(['message' => 'Offer created successfully']);
  }

  #[Test]
  public function it_can_update_offer_successfully()
  {
    // 1. البيانات
    $collectionSlug = "test-offer";
    $payload = [
      "conditions_logic" => "and",
      "description" => "تعديل الوصف",
      "benefit_config" => ["fixed_amount" => 50],
      "start_at" => "2026-04-01",
      "end_at" => "2026-05-01"
    ];

    $mockUpdatedData = ['id' => 1, 'name' => 'Test Offer Updated'];

    // 2. توقع استدعاء التحديث في الخدمة
    $this->offerServiceMock
      ->shouldReceive('update')
      ->once()
      ->andReturn($mockUpdatedData);

    // 3. بناء الـ Request
    $request = UpdateOfferRequest::create("/api/ecommerce/offers/{$collectionSlug}", 'PUT', $payload);
    $this->app->instance(UpdateOfferRequest::class, $request);

    // 4. تنفيذ التابع
    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->update($collectionSlug, $request);

    // 5. التحقق
    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Offer updated successfully',
        'data' => $mockUpdatedData
      ]);
  }

  #[Test]
  public function it_returns_offer_details_successfully()
  {
    $slug = 'special-offer';
    $mockOfferData = ['id' => 10, 'name' => 'Special Offer', 'slug' => $slug];

    $this->offerServiceMock
      ->shouldReceive('show')
      ->once()
      ->with($slug)
      ->andReturn($mockOfferData);

    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->show($slug);

    // مررنا request فارغ كمعامل ثانٍ
    $testResponse = $this->createTestResponse($response, request());

    $testResponse->assertStatus(200)
      ->assertJson(['data' => $mockOfferData]);
  }

  #[Test]
  public function it_returns_404_when_offer_is_not_found()
  {
    $slug = 'non-existent-offer';

    $this->offerServiceMock
      ->shouldReceive('show')
      ->once()
      ->with($slug)
      ->andReturn(null);

    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->show($slug);

    // مررنا request فارغ كمعامل ثانٍ
    $testResponse = $this->createTestResponse($response, request());

    $testResponse->assertStatus(404)
      ->assertJson(['message' => 'Offer not found']);
  }

  #[Test]
  public function it_can_delete_an_offer_successfully()
  {
    $slug = 'offer-to-delete';

    $this->offerServiceMock
      ->shouldReceive('delete')
      ->once()
      ->with($slug)
      ->andReturn(true);

    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->destroy($slug);

    // مررنا request فارغ كمعامل ثانٍ
    $testResponse = $this->createTestResponse($response, request());

    $testResponse->assertStatus(200)
      ->assertJson(['message' => 'Offer deleted successfully']);
  }

  #[Test]
  public function it_can_add_items_to_offer_successfully()
  {
    $slug = 'summer-sale';
    $payload = [
      'items' => [
        ['item_id' => 1, 'quantity' => 10]
      ]
    ];

    $this->offerServiceMock->shouldReceive('addItems')->once();

    // حل مشكلة الـ Null: نستخدم الدالة create من الـ Request ونحقن البيانات
    $request = \App\Domains\E_Commerce\Requests\InsertOfferItemsRequest::create(
      "/api/ecommerce/offers/{$slug}/items",
      'POST',
      $payload
    );

    // هذا الجزء السحري: نربط الـ Request بالـ Container ونحقن الـ Validator يدوياً لتجنب خطأ validated()
    $request->setContainer($this->app)->setRedirector($this->app->make(\Illuminate\Routing\Redirector::class));
    $request->replace($payload); // نضمن وجود البيانات في الجسم

    // محاكاة نجاح التوثيق (Validation)
    $validator = \Illuminate\Support\Facades\Validator::make($payload, [
      'items' => 'required|array'
    ]);
    $request->setValidator($validator);

    $this->app->instance(\App\Domains\E_Commerce\Requests\InsertOfferItemsRequest::class, $request);

    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->addItems($slug, $request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200);
  }

  #[Test]
  public function it_can_remove_items_from_offer_successfully()
  {
    $slug = 'summer-sale';
    $payload = ['items' => [1, 2]];

    $this->offerServiceMock->shouldReceive('removeItems')->once();

    $request = \App\Domains\E_Commerce\Requests\RemoveOfferItemsRequest::create(
      "/api/ecommerce/offers/{$slug}/items",
      'DELETE',
      $payload
    );

    // تكرار نفس الربط للـ Validator
    $request->setContainer($this->app)->setRedirector($this->app->make(\Illuminate\Routing\Redirector::class));
    $request->replace($payload);

    $validator = \Illuminate\Support\Facades\Validator::make($payload, [
      'items' => 'required|array'
    ]);
    $request->setValidator($validator);

    $this->app->instance(\App\Domains\E_Commerce\Requests\RemoveOfferItemsRequest::class, $request);

    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->removeItems($slug, $request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200);
  }
  #[Test]
  public function it_can_activate_offer_successfully()
  {
    $slug = 'winter-deal';
    $payload = ['is_active' => true];

    $this->offerServiceMock
      ->shouldReceive('activate')
      ->once()
      ->andReturn(null);

    $request = \App\Domains\E_Commerce\Requests\ActivationOfferRequest::create(
      "/api/ecommerce/offers/{$slug}/activate",
      'POST',
      $payload
    );
    $this->app->instance(\App\Domains\E_Commerce\Requests\ActivationOfferRequest::class, $request);

    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->activate($slug, $request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson(['message' => 'Offer activated successfully']);
  }

  #[Test]
  public function it_can_deactivate_offer_successfully()
  {
    $slug = 'winter-deal';
    $payload = ['is_active' => false];

    $this->offerServiceMock
      ->shouldReceive('deactivate')
      ->once()
      ->andReturn(null);

    $request = \App\Domains\E_Commerce\Requests\ActivationOfferRequest::create(
      "/api/ecommerce/offers/{$slug}/deactivate",
      'POST',
      $payload
    );
    $this->app->instance(\App\Domains\E_Commerce\Requests\ActivationOfferRequest::class, $request);

    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->deactivate($slug, $request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson(['message' => 'Offer deactivated successfully']);
  }

  #[Test]
  public function it_can_subscribe_to_an_offer_successfully()
  {
    // 1. تجهيز البيانات (المستخدم، الـ Slug، والبيانات المرسلة)
    $slug = 'summer-promo-2026';
    $mockUser = ['id' => 1];
    $payload = [
      'code' => 'SAVE50',
      'project_id' => 2
    ];

    // 2. توقع استدعاء الـ Service
    $this->offerServiceMock
      ->shouldReceive('subscribe')
      ->once()
      ->andReturn(null);

    // 3. بناء الـ Request وحقن الـ attributes
    $request = \App\Domains\E_Commerce\Requests\SubscribeOfferRequest::create(
      "/api/ecommerce/offers/{$slug}/subscribe",
      'POST',
      $payload
    );
    $request->attributes->set('auth_user', $mockUser);

    // حقن الـ instance في الحاوية
    $this->app->instance(\App\Domains\E_Commerce\Requests\SubscribeOfferRequest::class, $request);
    $this->app->instance('request', $request);

    // 4. تنفيذ التابع من الـ Controller
    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->subscribe($slug, $request);

    // 5. التحقق من النتيجة
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Offer subscribed successfully'
      ]);
  }

  #[Test]
  public function it_can_list_all_offers_for_a_specific_project()
  {
    // 1. تجهيز المعطيات (معرف المشروع والبيانات المتوقع عودتها)
    $projectId = 5;
    $mockOffers = [
      [
        'id' => 1,
        'name' => 'Ramadan Offer',
        'slug' => 'ramadan-offer',
        'is_active' => true
      ],
      [
        'id' => 2,
        'name' => 'Summer Sale',
        'slug' => 'summer-sale',
        'is_active' => false
      ]
    ];

    // 2. توقع استدعاء الـ Service وتمرير الـ project_id الصحيح له
    $this->offerServiceMock
      ->shouldReceive('index')
      ->once()
      ->with($projectId)
      ->andReturn($mockOffers);

    // 3. بناء الـ Request مع تمرير الـ project_id كـ Query Parameter
    $request = \Illuminate\Http\Request::create('/api/ecommerce/offers', 'GET', [
      'project_id' => $projectId
    ]);

    // حقن الـ Request في الـ Container
    $this->app->instance('request', $request);

    // 4. استدعاء التابع من الـ Controller
    $controller = new OfferController($this->offerServiceMock, $this->cmsApiMock);
    $response = $controller->index($request);

    // 5. التحقق من صحة الرد
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJsonCount(2, 'data') // التأكد من عودة عنصرين
      ->assertJson([
        'data' => [
          ['name' => 'Ramadan Offer'],
          ['name' => 'Summer Sale']
        ]
      ]);
  }
}
