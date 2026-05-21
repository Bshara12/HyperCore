<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Models\Domains\Notifications\Models\NotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->service = new NotificationPreferenceService();
});

/**
 * --------------------------------------------------------------------------
 * دالة مساعدة لتوليد كائن Actor حقيقي متكامل باستخدام الـ DTO Factory
 * --------------------------------------------------------------------------
 */
function createPreferenceMockActor(string $type, string $id, ?string $projectId = null): NotificationActor
{
    return NotificationActor::fromArray([
        'type'       => $type,
        'id'         => $id,
        'project_id' => $projectId ?? 'project-x',
    ]);
}
/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود listForActor()
 * --------------------------------------------------------------------------
 */
it('lists preferences ordered by topic_key and channel for a specific actor', function () {
  $actor = createMockActor('user', 'user-555', 'project-x');

  // إنشاء تفضيلات مبعثرة للتحقق من الترتيب الافتراضي (orderBy)
  NotificationPreference::create([
    'project_id' => 'project-x',
    'recipient_type' => 'user',
    'recipient_id' => 'user-555',
    'topic_key' => 'billing',
    'channel' => 'sms',
    'enabled' => true
  ]);

  NotificationPreference::create([
    'project_id' => 'project-x',
    'recipient_type' => 'user',
    'recipient_id' => 'user-555',
    'topic_key' => 'auth',
    'channel' => 'mail',
    'enabled' => true
  ]);

  $results = $this->service->listForActor($actor);

  expect($results)->toHaveCount(2)
    ->and($results->first()->topic_key)->toBe('auth') // ترتيب أبجدي حسب الـ topic_key
    ->and($results->last()->topic_key)->toBe('billing');
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود upsertForActor()
 * --------------------------------------------------------------------------
 */
it('updates existing preferences or creates new ones inside a transaction', function () {
  $actor = createMockActor('user', 'user-555', 'project-x');

  // 1. التفضيلات المبدئية المراد إرسالها للـ Upsert
  $inputPreferences = [
    [
      'topic_key' => 'security',
      'channel' => 'mail',
      'enabled' => false,
      'locale' => 'ar'
    ],
    [
      'topic_key' => 'marketing',
      'channel' => 'sms',
      'enabled' => true,
      'delivery_mode' => 'instant'
    ]
  ];

  $results = $this->service->upsertForActor($actor, $inputPreferences);
  expect($results)->toHaveCount(2);

  // 2. تحديث أحد التفضيلات الحالية لإجبار updateOrCreate على عمل Update لتغطية أسطر الـ Modification
  $updatePreferences = [
    [
      'topic_key' => 'security',
      'channel' => 'mail',
      'enabled' => true, // قمنا بتغييرها من false إلى true
      'locale' => 'en'
    ]
  ];

  $updatedResults = $this->service->upsertForActor($actor, $updatePreferences);

  // التحقق من الحفظ والتحديث بنجاح
  $securityPref = $updatedResults->where('topic_key', 'security')->first();
  expect($securityPref->enabled)->toBeTrue()
    ->and($securityPref->locale)->toBe('en');
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود isChannelEnabled() (التحقق الكامل من الـ Logic Conditions)
 * --------------------------------------------------------------------------
 */
it('returns true if no preference is configured for the channel (default state)', function () {
  $actor = createMockActor('user', 'user-555', 'project-x');

  // لم ننشئ أي سجل في الداتابيز، يجب أن تعيد true تلقائياً
  $isEnabled = $this->service->isChannelEnabled($actor, 'security', 'slack');

  expect($isEnabled)->toBeTrue();
});

it('returns false if the channel preference is explicitly disabled', function () {
  $actor = createMockActor('user', 'user-555', 'project-x');

  NotificationPreference::create([
    'project_id' => 'project-x',
    'recipient_type' => 'user',
    'recipient_id' => 'user-555',
    'topic_key' => 'security',
    'channel' => 'mail',
    'enabled' => false
  ]);

  $isEnabled = $this->service->isChannelEnabled($actor, 'security', 'mail');

  expect($isEnabled)->toBeFalse();
});

it('returns false if the preference is muted until a future date', function () {
  $actor = createMockActor('user', 'user-555', 'project-x');

  NotificationPreference::create([
    'project_id' => 'project-x',
    'recipient_type' => 'user',
    'recipient_id' => 'user-555',
    'topic_key' => 'security',
    'channel' => 'mail',
    'enabled' => true,
    'mute_until' => now()->addHours(2) // كتم الصوت لساعتين في المستقبل
  ]);

  $isEnabled = $this->service->isChannelEnabled($actor, 'security', 'mail');

  expect($isEnabled)->toBeFalse();
});

it('returns true if the mute duration has expired in the past', function () {
  $actor = createMockActor('user', 'user-555', 'project-x');

  NotificationPreference::create([
    'project_id' => 'project-x',
    'recipient_type' => 'user',
    'recipient_id' => 'user-555',
    'topic_key' => 'security',
    'channel' => 'mail',
    'enabled' => true,
    'mute_until' => now()->subHours(1) // الكتم انتهى قبل ساعة
  ]);

  $isEnabled = $this->service->isChannelEnabled($actor, 'security', 'mail');

  expect($isEnabled)->toBeTrue();
});

it('falls back to global preference if the specific topic key is missing but global is disabled', function () {
  $actor = createMockActor('user', 'user-555', 'project-x');

  // إنشاء تفضيل عام للمشروع (Global Preference) حيث الـ topic_key يحمل قيمة null
  NotificationPreference::create([
    'project_id' => 'project-x',
    'recipient_type' => 'user',
    'recipient_id' => 'user-555',
    'topic_key' => null,
    'channel' => 'mail',
    'enabled' => false
  ]);

  // نطلب البحث عن مفتاح خاص 'orders'، وبما أنه غير موجود، الاستعلام سيتراجع (Fallback) للـ null المكتوب بالـ orWhereNull
  $isEnabled = $this->service->isChannelEnabled($actor, 'orders', 'mail');

  expect($isEnabled)->toBeFalse();
});
