<?php

namespace Tests\Unit\Services\CMS;

use Tests\TestCase;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CMSApiClientTest extends TestCase
{
  protected CMSApiClient $cmsClient;
  protected string $fakeUrl = 'https://cms-service.test';

  protected function setUp(): void
  {
    parent::setUp();
    Config::set('services.cms_service.url', $this->fakeUrl);
    $this->cmsClient = new CMSApiClient();
  }

  #[Test]
  public function it_resolves_project_successfully(): void
  {
    // محاكاة رد ناجح يحتوي على المفتاح 'original'
    Http::fake([
      "{$this->fakeUrl}/api/projects/resolve" => Http::response([
        'original' => [
          'id' => 1,
          'name' => 'My Project'
        ]
      ], 200)
    ]);

    $result = $this->cmsClient->resolveProject();

    $this->assertEquals(1, $result['id']);
    $this->assertEquals('My Project', $result['name']);
  }

  #[Test]
  public function it_throws_exception_when_resolve_fails_with_json_message(): void
  {
    // الحالة الأولى للفشل: الرد يحتوي على JSON ومفتاح 'message'
    // هذا يغطي السطر الخاص بـ $response->json('message')
    Http::fake([
      "{$this->fakeUrl}/api/projects/resolve" => Http::response([
        'message' => 'Unauthorized Access'
      ], 401)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to resolve project in CMS: Unauthorized Access');

    $this->cmsClient->resolveProject();
  }

  #[Test]
  public function it_throws_exception_when_resolve_fails_with_raw_body(): void
  {
    // الحالة الثانية للفشل: الرد عبارة عن نص خام (Raw Body) وليس JSON
    // هذا يغطي السطر الخاص بـ substr($response->body(), 0, 200)
    $rawError = 'Internal Server Error Occurred at the Load Balancer level';

    Http::fake([
      "{$this->fakeUrl}/api/projects/resolve" => Http::response($rawError, 500)
    ]);

    $this->expectException(\Exception::class);
    // نتوقع أن تأخذ الدالة النص الخام كما هو لأنه أقل من 200 حرف
    $this->expectExceptionMessage('Failed to resolve project in CMS: ' . $rawError);

    $this->cmsClient->resolveProject();
  }

  #[Test]
  public function it_creates_collection_successfully(): void
  {
    $collectionData = ['name' => 'Products', 'slug' => 'products'];
    $successResponse = ['id' => 500, 'name' => 'Products'];

    // محاكاة رد ناجح (201 Created)
    Http::fake([
      "{$this->fakeUrl}/api/cms/collections" => Http::response($successResponse, 201)
    ]);

    $result = $this->cmsClient->createCollection($collectionData);

    // التحقق من صحة النتيجة المرتجعة
    $this->assertEquals(500, $result['id']);
    $this->assertEquals('Products', $result['name']);

    // التحقق من أن الطلب أُرسل كـ POST ومع البيانات الصحيحة
    Http::assertSent(function ($request) use ($collectionData) {
      return $request->method() === 'POST' &&
        $request->url() === "{$this->fakeUrl}/api/cms/collections" &&
        $request->data() === $collectionData;
    });
  }

  #[Test]
  public function it_throws_exception_when_creating_collection_fails_with_json_message(): void
  {
    // الحالة الأولى للفشل: خطأ في التحقق (Validation Error) مثلاً
    Http::fake([
      "{$this->fakeUrl}/api/cms/collections" => Http::response([
        'message' => 'The name field is required.'
      ], 422)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to create collection in CMS: The name field is required.');

    $this->cmsClient->createCollection(['slug' => 'no-name']);
  }

  #[Test]
  public function it_throws_exception_when_creating_collection_fails_with_raw_body(): void
  {
    // الحالة الثانية للفشل: خطأ تقني يعيد نصاً خاماً
    $serverError = 'Bad Gateway - Connection timeout';

    Http::fake([
      "{$this->fakeUrl}/api/cms/collections" => Http::response($serverError, 502)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to create collection in CMS: ' . $serverError);

    $this->cmsClient->createCollection(['name' => 'Test']);
  }

  #[Test]
  public function it_charges_booking_successfully_with_token(): void
  {
    $inputData = [
      'user_id' => 1,
      'user_name' => 'Ahmed',
      'project_id' => 10,
      'amount' => 100,
      'currency' => 'USD',
      'gateway' => 'stripe',
      'token' => 'tok_123'
    ];

    Http::fake([
      "{$this->fakeUrl}/api/payments/pay" => Http::response(['status' => 'paid'], 200)
    ]);

    $result = $this->cmsClient->chargeBooking($inputData);

    $this->assertEquals('paid', $result['status']);

    // التحقق من تحويل المفاتيح وإرسال التوكن
    Http::assertSent(function ($request) use ($inputData) {
      return $request['userId'] === $inputData['user_id'] &&
        $request['userName'] === $inputData['user_name'] &&
        $request['token'] === 'tok_123' &&
        $request['paymentType'] === 'full';
    });
  }

  #[Test]
  public function it_charges_booking_successfully_without_token(): void
  {
    // اختبار حالة عدم إرسال توكن لتغطية الـ ?? null
    $inputData = [
      'user_id' => 1,
      'user_name' => 'Ahmed',
      'project_id' => 10,
      'amount' => 100,
      'currency' => 'USD',
      'gateway' => 'stripe'
      // لا يوجد token هنا
    ];

    Http::fake([
      "{$this->fakeUrl}/api/payments/pay" => Http::response(['status' => 'paid'], 200)
    ]);

    $this->cmsClient->chargeBooking($inputData);

    Http::assertSent(fn($request) => $request['token'] === null);
  }

  #[Test]
  public function it_throws_exception_when_payment_fails_with_json_message(): void
  {
    Http::fake([
      "{$this->fakeUrl}/api/payments/pay" => Http::response(['message' => 'Insufficient Balance'], 402)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Payment failed: Insufficient Balance');

    $this->cmsClient->chargeBooking([
      'user_id' => 1,
      'user_name' => 'A',
      'project_id' => 1,
      'amount' => 10,
      'currency' => 'USD',
      'gateway' => 'stripe'
    ]);
  }

  #[Test]
  public function it_throws_exception_when_payment_fails_with_raw_body(): void
  {
    // تغطية الجزء الثاني من الـ ?? وهو substr($response->body(), 0, 200)
    $rawError = 'Payment Gateway Timeout Error';
    Http::fake([
      "{$this->fakeUrl}/api/payments/pay" => Http::response($rawError, 504)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Payment failed: ' . $rawError);

    $this->cmsClient->chargeBooking([
      'user_id' => 1,
      'user_name' => 'A',
      'project_id' => 1,
      'amount' => 10,
      'currency' => 'USD',
      'gateway' => 'stripe'
    ]);
  }

  #[Test]
  public function it_refunds_booking_successfully(): void
  {
    $inputData = [
      'payment_id' => 'pay_abc_123',
      'amount' => 50.75
    ];

    // محاكاة رد ناجح
    Http::fake([
      "{$this->fakeUrl}/api/payments/refund" => Http::response([
        'status' => 'refunded',
        'refund_id' => 'ref_999'
      ], 200)
    ]);

    $result = $this->cmsClient->refundBooking($inputData);

    // التحقق من صحة الرد
    $this->assertEquals('refunded', $result['status']);
    $this->assertEquals('ref_999', $result['refund_id']);

    // التحقق من تحويل المفاتيح من snake_case إلى camelCase
    Http::assertSent(function ($request) use ($inputData) {
      return $request->method() === 'POST' &&
        $request['paymentId'] === $inputData['payment_id'] &&
        $request['amount'] === $inputData['amount'];
    });
  }

  #[Test]
  public function it_throws_exception_when_refund_fails(): void
  {
    // محاكاة أي خطأ (مثلاً 400 Bad Request)
    Http::fake([
      "{$this->fakeUrl}/api/payments/refund" => Http::response([], 400)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Refund failed');

    $this->cmsClient->refundBooking([
      'payment_id' => 'invalid_id',
      'amount' => 0
    ]);
  }
}
