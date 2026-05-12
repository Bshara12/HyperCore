<?php

namespace Tests\Unit\Domains\Core\Traits;

use Tests\TestCase;
use App\Domains\Core\Traits\HasProjectHeaders;
use Illuminate\Http\Request;

class HasProjectHeadersTest extends TestCase
{
  private $testClass;

  protected function setUp(): void
  {
    parent::setUp();

    // إنشاء كلاس وهمي يطبق الـ Trait
    $this->testClass = new class {
      use HasProjectHeaders;
    };
  }

  /** @test */
  public function it_returns_headers_with_provided_project_id()
  {
    // إعداد: محاكاة وجود Token في الطلب
    $token = 'test-token-123';
    $request = Request::create('/', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $token);

    // ربط الطلب بالـ Container الخاص بـ Laravel ليتمكن request() من التقاطه
    $this->instance('request', $request);

    // التنفيذ: تمرير معرف المشروع يدوياً
    $headers = $this->testClass->projectHeaders('manual-id');

    // التحقق
    $this->assertEquals('application/json', $headers['Accept']);
    $this->assertEquals('application/json', $headers['Content-Type']);
    $this->assertEquals('manual-id', $headers['X-Project-Id']);
    $this->assertEquals('Bearer ' . $token, $headers['Authorization']);
  }

  /** @test */
  public function it_returns_headers_with_project_id_from_request_header()
  {
    // إعداد: وضع معرف المشروع في الـ Header الخاص بالطلب
    $token = 'test-token-456';
    $projectId = 'header-id-789';

    $request = Request::create('/', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $token);
    $request->headers->set('X-Project-Id', $projectId);

    $this->instance('request', $request);

    // التنفيذ: عدم تمرير أي باراميتر (يجب أن يسحب من الـ Header)
    $headers = $this->testClass->projectHeaders();

    // التحقق
    $this->assertEquals($projectId, $headers['X-Project-Id']);
    $this->assertEquals('Bearer ' . $token, $headers['Authorization']);
  }

  /** @test */
  public function it_returns_null_project_id_if_none_provided_and_none_in_header()
  {
    // إعداد طلب فارغ
    $request = Request::create('/', 'GET');
    $this->instance('request', $request);

    $headers = $this->testClass->projectHeaders();

    $this->assertNull($headers['X-Project-Id']);
    $this->assertEquals('Bearer ', $headers['Authorization']);
  }
}
