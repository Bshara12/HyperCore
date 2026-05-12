<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\EnsureBookingEnabled;
use App\Services\CMS\CMSApiClient;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class EnsureBookingEnabledTest extends TestCase
{
  #[Test]
  public function it_aborts_if_booking_module_is_not_enabled()
  {
    // 1. إنشاء Mock للـ CMSApiClient (رغم أنه لا يُستخدم مباشرة في handle حالياً لكنه مطلوب للـ Constructor)
    $cmsMock = Mockery::mock(CMSApiClient::class);
    $middleware = new EnsureBookingEnabled($cmsMock);

    // 2. تجهيز طلب يحتوي على بيانات مشروع لا يدعم الـ booking
    $request = Request::create('/test', 'GET');
    $request->merge([
      'project' => [
        'enabled_modules' => ['blog', 'shop']
      ]
    ]);

    // 3. التوقع بأن النظام سيرمي خطأ 403 (abort)
    try {
      $middleware->handle($request, function () {
        $this->fail('Middleware should not pass the request to the next closure.');
      });
    } catch (HttpException $e) {
      $this->assertEquals(403, $e->getStatusCode());
      $this->assertEquals('Booking module is not enabled for this project', $e->getMessage());
    }
  }
  #[Test]
  public function it_allows_request_if_booking_module_is_enabled()
  {
    $cmsMock = Mockery::mock(CMSApiClient::class);
    $middleware = new EnsureBookingEnabled($cmsMock);

    // 1. تجهيز طلب يحتوي على مشروع مفعّل به الـ booking
    $request = Request::create('/test', 'GET');
    $request->merge([
      'project' => [
        'enabled_modules' => ['booking', 'blog']
      ]
    ]);

    // 2. التنفيذ والتأكد من المرور للخطوة التالية
    $response = $middleware->handle($request, function ($req) {
      return response('Passed');
    });

    $this->assertEquals('Passed', $response->getContent());
  }
  #[Test]
  public function it_aborts_if_enabled_modules_key_is_missing()
  {
    $cmsMock = Mockery::mock(CMSApiClient::class);
    $middleware = new EnsureBookingEnabled($cmsMock);

    // حالة تغطية لسطر الـ ?? []
    $request = Request::create('/test', 'GET');
    $request->merge(['project' => []]);

    $this->expectException(HttpException::class);
    $this->expectExceptionMessage('Booking module is not enabled for this project');

    $middleware->handle($request, function () {});
  }
}
