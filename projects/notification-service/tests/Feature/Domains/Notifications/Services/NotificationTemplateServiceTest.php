<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Services\NotificationTemplateService;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->service = new NotificationTemplateService();
});

/**
 * --------------------------------------------------------------------------
 * دالة مساعدة لبناء كائن حقيقي من الـ DTO وتفادي قيود الـ final class
 * --------------------------------------------------------------------------
 */
function createMockActor(string $type, string $id, ?string $projectId = null): NotificationActor
{
  $reflection = new ReflectionClass(NotificationActor::class);
  $actor = $reflection->newInstanceWithoutConstructor();

  setPrivateProperty($actor, 'type', $type);
  setPrivateProperty($actor, 'id', $id);
  setPrivateProperty($actor, 'projectId', $projectId);

  return $actor;
}

function setPrivateProperty(object $object, string $propertyName, mixed $value): void
{
  try {
    $reflection = new ReflectionProperty($object, $propertyName);
    $reflection->setValue($object, $value);
  } catch (ReflectionException $e) {
    $object->{$propertyName} = $value;
  }
}

/**
 * --------------------------------------------------------------------------
 * اختبار ميثود listForActor() والترتيب المركب
 * --------------------------------------------------------------------------
 */
it('lists templates ordered by key ascending and version descending for an actor', function () {
  $actor = createMockActor('user', 'user-100', 'project-x');

  // إنشاء قالب بإصدار قديم
  NotificationTemplate::create([
    'project_id' => 'project-x',
    'key' => 'welcome_email',
    'version' => 1,
    'subject_template' => 'Welcome v1',
    'body_template' => '...',
    'is_active' => true
  ]);

  // إنشاء نفس القالب بإصدار أحدث (يجب أن يظهر أولاً بسبب orderByDesc)
  NotificationTemplate::create([
    'project_id' => 'project-x',
    'key' => 'welcome_email',
    'version' => 2,
    'subject_template' => 'Welcome v2',
    'body_template' => '...',
    'is_active' => true
  ]);

  // إنشاء قالب بمفتاح آخر يقع أبجدياً قبل المفتاح الأول (يجب أن يظهر في بداية المصفوفة)
  NotificationTemplate::create([
    'project_id' => 'project-x',
    'key' => 'alert_sms',
    'version' => 1,
    'subject_template' => 'Alert',
    'body_template' => '...',
    'is_active' => true
  ]);

  $results = $this->service->listForActor($actor);

  expect($results)->toHaveCount(3)
    ->and($results->get(0)->key)->toBe('alert_sms')        // أولاً الترتيب الأبجدي للمفاتيح
    ->and($results->get(1)->key)->toBe('welcome_email')    // ثانياً المجموعة التالية
    ->and($results->get(1)->version)->toBe(2)              // داخل المجموعة: الإصدار الأحدث يظهر أولاً
    ->and($results->get(2)->version)->toBe(1);
});

/**
 * --------------------------------------------------------------------------
 * اختبارات الـ CRUD (Create & Update)
 * --------------------------------------------------------------------------
 */
it('creates and updates notification templates successfully', function () {
  $data = [
    'project_id' => 'project-x',
    'key' => 'invoice_ready',
    'version' => 1,
    'subject_template' => 'Your invoice is ready',
    'body_template' => 'Hello {{name}}',
    'is_active' => false
  ];

  // 1. فحص الإنشاء
  $template = $this->service->create($data);
  expect($template->id)->not->toBeNull()
    ->and($template->key)->toBe('invoice_ready');

  // 2. فحص التحديث
  $updatedTemplate = $this->service->update($template, [
    'subject_template' => 'Updated Invoice Title'
  ]);

  expect($updatedTemplate->subject_template)->toBe('Updated Invoice Title');
});

/**
 * --------------------------------------------------------------------------
 * اختبارات التحكم بالحالة الفعالة (Activate & Deactivate)
 * --------------------------------------------------------------------------
 */
it('can activate and deactivate a notification template via forceFill', function () {
  $template = NotificationTemplate::create([
    'project_id' => 'project-x',
    'key' => 'test_toggle',
    'version' => 1,
    'subject_template' => 'Title',
    'body_template' => 'Body',
    'is_active' => false // أنشئ كغير فعال
  ]);

  // 1. التفعيل
  $activated = $this->service->activate($template);
  expect($activated->is_active)->toBeTrue();

  // 2. إلغاء التفعيل
  $deactivated = $this->service->deactivate($activated);
  expect($deactivated->is_active)->toBeFalse();
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود findForActor()
 * --------------------------------------------------------------------------
 */
it('finds a template by id for a valid actor scope or throws an exception', function () {
  $actor = createMockActor('user', 'user-100', 'project-x');

  $template = NotificationTemplate::create([
    'project_id' => 'project-x',
    'key' => 'find_me',
    'version' => 1,
    'subject_template' => 'Title',
    'body_template' => 'Body',
    'is_active' => true
  ]);

  // 1. حالة النجاح ضمن الـ Scope
  $found = $this->service->findForActor($actor, $template->id);
  expect($found->id)->toBe($template->id);

  // 2. حالة الفشل عند تمرير معرف غير موجود
  expect(fn() => $this->service->findForActor($actor, 'non-existent-id'))
    ->toThrow(ModelNotFoundException::class);
});
