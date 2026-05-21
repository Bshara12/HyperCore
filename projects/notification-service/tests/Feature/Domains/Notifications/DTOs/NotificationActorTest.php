<?php

namespace Tests\Feature\Domains\Notifications\DTOs;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Domains\Notifications\DTOs\NotificationActor;

class NotificationActorTest extends TestCase
{
  // --------------------------------------------------------------------
  // 1. اختبار البناء من الـ Request كـ User
  // --------------------------------------------------------------------
  public function test_can_create_actor_from_request_as_user()
  {
    $request = new Request();

    // محاكاة الـ Attributes القادمة من الـ Middleware
    $request->attributes->set('auth_user', [
      'id' => 456,
      'permessions' => ['notifications.create', 'notifications.view']
    ]);
    $request->attributes->set('project', ['string' => 'proj_abc']);

    // محاكاة الـ Traceability Headers والبيانات الجانبية
    $request->headers->set('X-Request-Id', 'req-111');
    $request->headers->set('X-Correlation-Id', 'corr-222');
    $request->headers->set('X-Causation-Id', 'caus-332');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');
    $request->headers->set('User-Agent', 'FerasDev-Browser');

    $actor = NotificationActor::fromRequest($request);

    $this->assertTrue($actor->isUser());
    $this->assertFalse($actor->isService());
    $this->assertEquals('user', $actor->type);
    $this->assertEquals(456, $actor->id);
    $this->assertEquals('proj_abc', $actor->projectId);
    $this->assertEquals('req-111', $actor->requestId);
    $this->assertEquals('corr-222', $actor->correlationId);
    $this->assertEquals('caus-332', $actor->causationId);
    $this->assertEquals('192.168.1.1', $actor->ip);
    $this->assertEquals('FerasDev-Browser', $actor->userAgent);

    // فحص الصلاحيات
    $this->assertTrue($actor->hasPermission('notifications.create'));
    $this->assertFalse($actor->hasPermission('admin.delete'));
  }

  // --------------------------------------------------------------------
  // 2. اختبار البناء من الـ Request كـ Service Client
  // --------------------------------------------------------------------
  public function test_can_create_actor_from_request_as_service()
  {
    $request = new Request();

    // كائن الخدمة (Service Client)
    $serviceClient = (object) [
      'id' => 'srv_payment_01',
      'scopes' => ['service.send']
    ];

    $request->attributes->set('auth_service_client', $serviceClient);
    $request->attributes->set('project', ['string' => 'proj_service_core']);

    $actor = NotificationActor::fromRequest($request);

    $this->assertTrue($actor->isService());
    $this->assertFalse($actor->isUser());
    $this->assertEquals('service', $actor->type);
    $this->assertEquals('srv_payment_01', $actor->id);
    $this->assertEquals('proj_service_core', $actor->projectId);
    $this->assertTrue($actor->hasPermission('service.send'));
    $this->assertArrayHasKey('service', $actor->raw);
  }

  // --------------------------------------------------------------------
  // 3. اختبار البناء من مصفوفة عبر fromArray
  // --------------------------------------------------------------------
  public function test_can_create_actor_from_array()
  {
    $data = [
      'type' => 'user',
      'id' => 999,
      'permessions' => ['user.profile'],
      'project_id' => 'proj_xyz',
      'request_id' => 'req-999',
      'correlation_id' => 'corr-999',
      'causation_id' => 'caus-999',
      'ip' => '127.0.0.1',
      'user_agent' => 'PostmanRuntime',
      'raw' => ['name' => 'Feras']
    ];

    $actor = NotificationActor::fromArray($data);

    $this->assertEquals('user', $actor->type);
    $this->assertEquals(999, $actor->id);
    $this->assertEquals('proj_xyz', $actor->projectId);
    $this->assertTrue($actor->hasPermission('user.profile'));
    $this->assertEquals(['name' => 'Feras'], $actor->raw);
  }

  // --------------------------------------------------------------------
  // 4. اختبار دالة الـ snapshot() وتطابق الهيكل المستخرج
  // --------------------------------------------------------------------
  public function test_can_generate_correct_snapshot_array()
  {
    $actor = new NotificationActor(
      type: 'user',
      id: 'user_01',
      permessions: ['read'],
      projectId: 'proj_01',
      raw: ['meta' => 'data'],
      requestId: 'req_id_1',
      correlationId: 'corr_id_2',
      causationId: 'caus_id_3',
      ip: '10.0.0.1',
      userAgent: 'Mozilla'
    );

    $snapshot = $actor->snapshot();

    $expected = [
      'type' => 'user',
      'id' => 'user_01',
      'project_id' => 'proj_01',
      'permessions' => ['read'],
      'request_id' => 'req_id_1',
      'correlation_id' => 'corr_id_2',
      'causation_id' => 'caus_id_3',
      'ip' => '10.0.0.1',
      'user_agent' => 'Mozilla',
      'raw' => ['meta' => 'data'],
    ];

    $this->assertEquals($expected, $snapshot);
  }
}
