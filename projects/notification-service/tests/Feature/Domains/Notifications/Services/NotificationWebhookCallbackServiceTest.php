<?php

use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Domains\Notifications\Services\NotificationWebhookCallbackService;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
  // 1. عمل Mock لخدمة الـ Delivery الممررة عبر الـ Constructor
  $this->deliveryServiceMock = Mockery::mock(NotificationDeliveryService::class);

  // حقن الـ Mock داخل خدمة الـ Webhook
  $this->service = new NotificationWebhookCallbackService($this->deliveryServiceMock);

  // 2. إنشاء إشعار يحمل الـ Webhook Secret داخل الـ metadata لاختبار الحماية
  $this->notification = (new Notification())->forceFill([
    'project_id'     => 'project-123',
    'recipient_type' => 'user',
    'recipient_id'   => 'user-777',
    'title'          => 'إشعار فواتير',
    'body'           => 'محتوى الإشعار',
    'status'         => 'queued',
    'metadata'       => [
      'webhook' => [
        'secret' => 'super-secret-key'
      ]
    ]
  ]);
  $this->notification->save();

  // 3. إنشاء سجل تسليم (Delivery) مرتبط بالإشعار
  $this->delivery = (new NotificationDelivery())->forceFill([
    'notification_id' => $this->notification->id,
    'channel'         => 'webhook',
    'status'          => 'pending',
    'attempts'        => 1,
    'max_attempts'    => 3,
    'payload_snapshot' => []
  ]);
  $this->delivery->save();
});

/**
 * --------------------------------------------------------------------------
 * دالة مساعدة لتوليد كائن الـ Request يحمل الـ Header والـ Body والـ Signature
 * --------------------------------------------------------------------------
 */
function createWebhookRequest(string $body, string $signatureHeader): Request
{
  return Request::create(
    uri: '/webhooks/notifications',
    method: 'POST',
    content: $body,
    server: ['HTTP_X_Webhook_Signature' => $signatureHeader]
  );
}

/**
 * --------------------------------------------------------------------------
 * اختبارات التحقق من التوقيع الرقمي والأمن (verifySignature)
 * --------------------------------------------------------------------------
 */
it('throws 401 exception if webhook signature is invalid', function () {
  $body = json_encode(['status' => 'delivered']);

  // نمرر توقيع خاطئ ومزيف لنختبر رمي الاستثناء وحماية النظام
  $request = createWebhookRequest($body, 'wrong-signature-hash');

  $data = [
    'delivery_id' => $this->delivery->id,
    'status'      => 'delivered'
  ];

  expect(fn() => $this->service->handle($request, $data))
    ->toThrow(HttpException::class, 'Invalid webhook signature.');
});

it('skips signature verification if webhook secret is missing in metadata', function () {
  // تحديث الإشعار ليصبح بلا سيكرت لتغطية سطر الـ "if (! $secret)" المعاكس
  $this->notification->update(['metadata' => []]);

  $body = json_encode(['status' => 'delivered']);
  $request = createWebhookRequest($body, ''); // بدون توقيع بالكامل

  $data = [
    'delivery_id' => $this->delivery->id,
    'status'      => 'delivered'
  ];

  // نتوقع استدعاء ميثود markDelivered في خدمة الدليفري لأن عملية التوثيق تم تجاوزها بنجاح
  $this->deliveryServiceMock->shouldReceive('markDelivered')
    ->once()
    ->with(Mockery::type(NotificationDelivery::class));

  $result = $this->service->handle($request, $data);
  expect($result->id)->toBe($this->delivery->id);
});

/**
 * --------------------------------------------------------------------------
 * اختبار خطأ عدم وجود سجل الـ Delivery (404)
 * --------------------------------------------------------------------------
 */
it('aborts with 404 if delivery record does not exist', function () {
  $request = createWebhookRequest('{}', '');
  $data = ['delivery_id' => 'non-existent-id', 'status' => 'delivered'];

  expect(fn() => $this->service->handle($request, $data))
    ->toThrow(HttpException::class, 'Delivery not found.');
});

/**
 * --------------------------------------------------------------------------
 * اختبار خرائط الحالات بالكامل داخل الـ Match Expression (التغطية الشاملة للـ Match)
 * --------------------------------------------------------------------------
 */
/**
 * --------------------------------------------------------------------------
 * اختبار خرائط الحالات بالكامل داخل الـ Match Expression (التغطية الشاملة للـ Match)
 * --------------------------------------------------------------------------
 */
it('processes and marks status successfully according to provider callback action', function (string $incomingStatus, string $expectedServiceMethod) {
    $body = json_encode(['status' => $incomingStatus, 'provider_message_id' => 'msg-xyz-123']);
    
    // حساب التوقيع الصحيح بناءً على الـ Secret لمحاكاة المزود الحقيقي
    $correctSignature = hash_hmac('sha256', $body, 'super-secret-key');
    $request = createWebhookRequest($body, $correctSignature);

    $data = [
        'delivery_id'         => $this->delivery->id,
        'status'              => $incomingStatus,
        'provider_message_id' => 'msg-xyz-123'
    ];

    // 💡 التعديل: بناء توقعات ديناميكية صارمة تطابق بارامترات كل ميثود في الـ DeliveryService
    if ($expectedServiceMethod === 'markFailed') {
        $data['error_code'] = 'LIMIT_EXCEEDED';
        $data['error_message'] = 'Rate limit hit.';
        
        $this->deliveryServiceMock->shouldReceive('markFailed')
            ->once()
            ->with(Mockery::type(NotificationDelivery::class), 'LIMIT_EXCEEDED', 'Rate limit hit.', Mockery::any());
    } elseif ($expectedServiceMethod === 'markSkipped') {
        // 💡 قفل بارامتر النص الإضافي الخاص بالـ skipped
        $this->deliveryServiceMock->shouldReceive('markSkipped')
            ->once()
            ->with(Mockery::type(NotificationDelivery::class), 'Webhook callback marked as skipped.');
    } else {
        $this->deliveryServiceMock->shouldReceive($expectedServiceMethod)
            ->once()
            ->with(Mockery::type(NotificationDelivery::class));
    }

    $result = $this->service->handle($request, $data);

    // التأكد من حفظ بيانات الـ provider_message_id والـ provider الجديد بـ forceFill
    expect($result->provider)->toBe('webhook')
        ->and($result->provider_message_id)->toBe('msg-xyz-123');

})->with([
    ['delivered', 'markDelivered'],
    ['processed', 'markDelivered'],
    ['sent',      'markSent'],
    ['received',  'markSent'],
    ['skipped',   'markSkipped'], // 🎯 السطر الذي كان يسبب الفشل
    ['failed',    'markFailed'],
]);

/**
 * --------------------------------------------------------------------------
 * اختبار الـ Default للـ Match Expression إذا جاءت حالة غير مدعومة
 * --------------------------------------------------------------------------
 */
it('returns original delivery object without modification for unsupported status codes', function () {
  $body = json_encode(['status' => 'unknown_action']);
  $correctSignature = hash_hmac('sha256', $body, 'super-secret-key');
  $request = createWebhookRequest($body, $correctSignature);

  $data = [
    'delivery_id' => $this->delivery->id,
    'status'      => 'unknown_action'
  ];

  // لا يجب استدعاء أي عمليات تعديل حالة على الـ mock
  $this->deliveryServiceMock->shouldNotReceive('markDelivered', 'markSent', 'markFailed', 'markSkipped');

  $result = $this->service->handle($request, $data);
  expect($result->id)->toBe($this->delivery->id);
});

afterEach(function () {
  Mockery::close();
});
