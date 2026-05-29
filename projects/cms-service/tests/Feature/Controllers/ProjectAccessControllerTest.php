<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\ProjectAccessController;
use App\Domains\Auth\Service\ProjectAccessService;
use App\Domains\Auth\Requests\CheckProjectAccessRequest;
use App\Domains\Auth\DTOs\CheckProjectAccessDto;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ProjectAccessControllerTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_returns_true_when_user_has_access()
  {
    // 1. إعداد الـ Mock للخدمة والطلب
    $service = Mockery::mock(ProjectAccessService::class);
    $request = Mockery::mock(CheckProjectAccessRequest::class);

    // محاكاة خصائص الطلب
    $request->user_id = 101;
    $request->shouldReceive('header')
      ->with('X-Project-Key')
      ->andReturn('project-secret-key');

    // 2. إعداد توقع الخدمة (التحقق من أن الـ DTO تم إنشاؤه بقيم صحيحة)
    $service->shouldReceive('check')
      ->once()
      ->with(Mockery::on(function ($dto) {
        return $dto instanceof CheckProjectAccessDto &&
          $dto->userId === 101 &&
          $dto->projectKey === 'project-secret-key';
      }))
      ->andReturn(true);

    // 3. التنفيذ (بما أن التابع يستخدم Method Injection، نمرر الـ Mocks مباشرة)
    $controller = new ProjectAccessController();
    $response = $controller->check($request, $service);

    // 4. التأكيد
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(['has_access' => true], $response->getData(true));
  }

  #[Test]
  public function it_returns_false_when_user_does_not_have_access()
  {
    $service = Mockery::mock(ProjectAccessService::class);
    $request = Mockery::mock(CheckProjectAccessRequest::class);

    $request->user_id = 202;
    $request->shouldReceive('header')
      ->with('X-Project-Key')
      ->andReturn('wrong-key');

    // إرجاع false من الخدمة
    $service->shouldReceive('check')
      ->once()
      ->andReturn(false);

    $controller = new ProjectAccessController();
    $response = $controller->check($request, $service);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(['has_access' => false], $response->getData(true));
  }
}
