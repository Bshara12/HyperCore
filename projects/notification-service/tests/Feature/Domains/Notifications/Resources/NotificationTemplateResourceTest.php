<?php

use App\Domains\Notifications\Resources\NotificationTemplateResource;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;

beforeEach(function () {
  // تثبيت الوقت عند بداية الثانية لتجنب مشاكل الـ Microseconds أثناء الـ JSON Serialization
  $this->now = Carbon::now()->startOfSecond();

  // إنشاء كائن حقيقي فارغ وحقن الخصائص بـ forceFill
  $this->template = (new NotificationTemplate())->forceFill([
    'id' => 'tmpl-123',
    'project_id' => 'project-abc',
    'key' => 'welcome.email',
    'channel' => 'email',
    'locale' => 'ar',
    'version' => 1, // 💡 تم التعديل إلى رقم صحيح ليتوافق مع الـ Cast الخاص بالموديل
    'subject_template' => 'أهلاً بك يا {{ name }} في منصتنا!',
    'body_template' => '<p>مرحباً بك، يسعدنا انضمامك إلينا...</p>',
    'variables_schema' => [
      'type' => 'object',
      'properties' => [
        'name' => ['type' => 'string']
      ],
      'required' => ['name']
    ],
    'defaults' => ['name' => 'المستخدم العزيز'],
    'is_active' => true,
  ]);

  // تعيين التواريخ ككائنات Carbon
  $this->template->created_at = $this->now;
  $this->template->updated_at = $this->now;
});

it('transforms the notification template resource attributes correctly into array', function () {
  // تجهيز الـ Request الوهمي
  $request = Request::create('/api/templates', 'GET');

  $resource = new NotificationTemplateResource($this->template);
  $result = $resource->toArray($request);

  // التحقق من صحة نقل البيانات والـ ISO Formatting للتواريخ والهياكل المعقدة
  expect($result)->toBeArray()
    ->and($result['id'])->toBe('tmpl-123')
    ->and($result['project_id'])->toBe('project-abc')
    ->and($result['key'])->toBe('welcome.email')
    ->and($result['channel'])->toBe('email')
    ->and($result['locale'])->toBe('ar')
    ->and($result['version'])->toBe(1) // 💡 تم التعديل هنا أيضاً ليطابق الرقم 1
    ->and($result['subject_template'])->toBe('أهلاً بك يا {{ name }} في منصتنا!')
    ->and($result['body_template'])->toBe('<p>مرحباً بك، يسعدنا انضمامك إلينا...</p>')
    ->and($result['variables_schema'])->toBe([
      'type' => 'object',
      'properties' => [
        'name' => ['type' => 'string']
      ],
      'required' => ['name']
    ])
    ->and($result['defaults'])->toBe(['name' => 'المستخدم العزيز'])
    ->and($result['is_active'])->toBeTrue()
    ->and($result['created_at'])->toBe($this->template->created_at->toISOString())
    ->and($result['updated_at'])->toBe($this->template->updated_at->toISOString());
});

it('handles optional nullable template dates correctly', function () {
  // تعيين التواريخ القابلة للإلغاء إلى null لاختبار الـ optional() Helper
  $this->template->created_at = null;
  $this->template->updated_at = null;

  $request = Request::create('/api/templates', 'GET');
  $resource = new NotificationTemplateResource($this->template);
  $result = $resource->toArray($request);

  // التأكد من عدم انهيار الكود ورجوع الحقول بـ null بأمان
  expect($result['created_at'])->toBeNull()
    ->and($result['updated_at'])->toBeNull();
});
