<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\DataEntryPublishController;
use App\Domains\CMS\Actions\Data\PublishDataEntryAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\ParameterBag;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class DataEntryPublishControllerTest extends TestCase
{
  private DataEntryPublishController $controller;
  private $publishActionMock;

  protected function setUp(): void
  {
    parent::setUp();

    // محاكاة الأكشن المسؤول عن النشر
    $this->publishActionMock = Mockery::mock(PublishDataEntryAction::class);
    $this->controller = new DataEntryPublishController();
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_publishes_entry_successfully_when_auth_user_is_an_array()
  {
    // 🎯 المستهدف: تغطية فرع الـ if (المستخدم مصفوفة)
    $entrySlug = 'awesome-entry-slug';
    $userId = 10;

    $requestMock = Mockery::mock(Request::class);
    $requestMock->attributes = new ParameterBag([
      'auth_user' => ['id' => $userId] // تمرير مصفوفة
    ]);

    // توقعات الأكشن: نتوقع استدعاء execute بالـ slug والمعرف 10
    $expectedResult = ['status' => 'success', 'message' => 'Published!'];
    $this->publishActionMock->shouldReceive('execute')
      ->once()
      ->with($entrySlug, $userId)
      ->andReturn($expectedResult);

    // التنفيذ الفعلي (استدعاء الكنترولر كـ كائن قابل للاستدعاء __invoke)
    $response = ($this->controller)($entrySlug, $requestMock, $this->publishActionMock);

    // التأكيدات
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode($expectedResult), $response->getContent());
  }

  #[Test]
  public function it_publishes_entry_successfully_when_auth_user_is_an_object()
  {
    // 🎯 المستهدف: تغطية فرع الـ elseif (المستخدم كائن)
    $entrySlug = 'another-entry-slug';
    $userId = 20;

    $requestMock = Mockery::mock(Request::class);
    $requestMock->attributes = new ParameterBag([
      'auth_user' => (object) ['id' => $userId] // تمرير كائن
    ]);

    $expectedResult = ['status' => 'success'];
    $this->publishActionMock->shouldReceive('execute')
      ->once()
      ->with($entrySlug, $userId)
      ->andReturn($expectedResult);

    $response = ($this->controller)($entrySlug, $requestMock, $this->publishActionMock);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_publishes_entry_and_falls_back_to_auth_helper_if_user_attribute_is_missing()
  {
    // 🎯 المستهدف: تغطية السطر $userId = $userId ?? auth()->id();
    $entrySlug = 'fallback-entry-slug';
    $fallbackUserId = 99;

    $requestMock = Mockery::mock(Request::class);

    // نمرر الـ auth_user فارغاً تماماً لنجبره على الانتقال للفالباك
    $requestMock->attributes = new ParameterBag([
      'auth_user' => null
    ]);

    // محاكاة واجهة الـ Auth الفعالة في لارافيل لتعيد المعرف 99 عند طلب auth()->id()
    Auth::shouldReceive('id')->once()->andReturn($fallbackUserId);

    $expectedResult = ['status' => 'success_via_fallback'];
    $this->publishActionMock->shouldReceive('execute')
      ->once()
      ->with($entrySlug, $fallbackUserId)
      ->andReturn($expectedResult);

    $response = ($this->controller)($entrySlug, $requestMock, $this->publishActionMock);

    $this->assertEquals(200, $response->getStatusCode());
  }
}
