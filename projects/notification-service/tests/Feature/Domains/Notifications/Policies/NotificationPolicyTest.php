<?php

namespace Tests\Feature\Domains\Notifications\Policies;

use Tests\TestCase;
use App\Domains\Notifications\Policies\NotificationPolicy;
use App\Domains\Notifications\DTOs\NotificationActor;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class NotificationPolicyTest extends TestCase
{
  private NotificationPolicy $policy;

  protected function setUp(): void
  {
    parent::setUp();

    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('recipient_type')->nullable();
      $table->string('recipient_id')->nullable();
      $table->timestamps();
    });

    $this->policy = new NotificationPolicy();
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notifications');

    parent::tearDown();
  }

  /**
   * 💡 الحل السحري والجذري: بناء كائن حقيقي 100% لتخطي الـ TypeError،
   * ثم استخدام الـ Reflection لحقن الصلاحيات والخصائص في الحقول الخلفية للكلاس مباشرة.
   */
  /**
     * بناء الـ Actor باستخدام الـ Typo الصحيح المخزن في الـ DTO (permessions)
     * واستدعاء الـ Constructor مباشرة لتفادي أي تعقيد.
     */
    private function createRealActor(string $type, array $permissions = [], ?string $projectId = null, ?string $id = 'test-id'): NotificationActor
    {
        return new NotificationActor(
            type: $type,
            id: $id,
            permessions: $permissions, // 💡 تم التعديل لتطابق الـ Typo في الـ DTO
            projectId: $projectId,
            raw: []
        );
    }

  // --------------------------------------------------------------------
  // 1. اختبار دالة create
  // --------------------------------------------------------------------
  public function test_create_allows_service_with_correct_permissions()
  {
    $actor = $this->createRealActor('service', ['notifications.create']);
    $this->assertTrue($this->policy->create($actor));
  }

  public function test_create_allows_service_with_manage_permission()
  {
    $actor = $this->createRealActor('service', ['notifications.manage']);
    $this->assertTrue($this->policy->create($actor));
  }

  public function test_create_denies_service_without_permissions()
  {
    $actor = $this->createRealActor('service', ['other.permission']);
    $this->assertFalse($this->policy->create($actor));
  }

  public function test_create_allows_user_with_correct_permissions()
  {
    $actor = $this->createRealActor('user', ['notifications.create']);
    $this->assertTrue($this->policy->create($actor));
  }

  public function test_create_denies_user_without_permissions()
  {
    $actor = $this->createRealActor('user', ['other.permission']);
    $this->assertFalse($this->policy->create($actor));
  }

  // --------------------------------------------------------------------
  // 2. اختبار دالة createSystem
  // --------------------------------------------------------------------
  public function test_create_system_allows_services_only()
  {
    $serviceActor = $this->createRealActor('service');
    $userActor = $this->createRealActor('user');

    $this->assertTrue($this->policy->createSystem($serviceActor));
    $this->assertFalse($this->policy->createSystem($userActor));
  }

  // --------------------------------------------------------------------
  // 3. اختبار دالة viewAny
  // --------------------------------------------------------------------
  public function test_view_any_allows_service_with_read_any_permission()
  {
    $actor = $this->createRealActor('service', ['notifications.read.any']);
    $this->assertTrue($this->policy->viewAny($actor));
  }

  public function test_view_any_allows_user_with_matching_project_id()
  {
    $actor = $this->createRealActor('user', [], 'proj-99');

    $this->assertTrue($this->policy->viewAny($actor, 'proj-99'));
    $this->assertFalse($this->policy->viewAny($actor, 'proj-different'));
  }

  // --------------------------------------------------------------------
  // 4. اختبار دالة view
  // --------------------------------------------------------------------
  public function test_view_allows_service_with_manage_permission()
  {
    $actor = $this->createRealActor('service', ['notifications.manage']);
    $notification = Notification::create();

    $this->assertTrue($this->policy->view($actor, $notification));
  }

  public function test_view_allows_user_if_they_are_the_recipient()
  {
    $actor = $this->createRealActor('user', [], null, 'feras-123');

    // إشعار موجه لنفس المستخدم
    $notification = Notification::create([
      'recipient_type' => 'user',
      'recipient_id' => 'feras-123'
    ]);

    $this->assertTrue($this->policy->view($actor, $notification));

    // إشعار موجه لمستخدم آخر
    $otherNotification = Notification::create([
      'recipient_type' => 'user',
      'recipient_id' => 'other-user'
    ]);

    $this->assertFalse($this->policy->view($actor, $otherNotification));
  }

  // --------------------------------------------------------------------
  // 5. اختبار دالة markAsRead (تعتمد على view داخلياً)
  // --------------------------------------------------------------------
  public function test_mark_as_read_mirrors_view_behavior()
  {
    $actor = $this->createRealActor('service', ['notifications.read.any']);
    $notification = Notification::create();

    $this->assertTrue($this->policy->markAsRead($actor, $notification));
  }

  // --------------------------------------------------------------------
  // 6. اختبار دالة markAllAsRead
  // --------------------------------------------------------------------
  public function test_mark_all_as_read_allows_service_with_manage_permission()
  {
    $actor = $this->createRealActor('service', ['notifications.manage']);
    $this->assertTrue($this->policy->markAllAsRead($actor));
  }

  public function test_mark_all_as_read_allows_user_with_matching_project_id()
  {
    $actor = $this->createRealActor('user', [], 'proj-active');

    $this->assertTrue($this->policy->markAllAsRead($actor, 'proj-active'));
    $this->assertFalse($this->policy->markAllAsRead($actor, 'proj-other'));
  }
}
