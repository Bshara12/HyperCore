<?php

use App\Domains\Notifications\Resources\NotificationBatchResource;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;

beforeEach(function () {
  // تثبيت الوقت عند بداية الثانية لتجنب مشاكل الـ Microseconds أثناء الـ Serialization
  $this->now = Carbon::now()->startOfSecond();

  // 1. إنشاء كائن حقيقي فارغ وحقن الخصائص بـ forceFill لتفادي قيود الـ Mockery
  $this->batch = (new NotificationBatch())->forceFill([
    'id' => 'batch-123',
    'project_id' => 'project-xyz',
    'created_by_type' => 'service',
    'created_by_id' => 'service-admin',
    'source_service' => 'marketing_engine',
    'source_event_type' => 'campaign.broadcast',
    'audience_type' => 'segment',
    'audience_query' => ['country' => 'SA', 'active' => true],
    'status' => 'processing',
    'dedupe_key' => 'dedupe_campaign_55',
    'total_targets' => 1500,
    'processed_targets' => 750,
  ]);

  // 2. تعيين كائنات التواريخ كاملة للـ Happy Path
  $this->batch->scheduled_at = $this->now->copy()->addHour();
  $this->batch->started_at = $this->now;
  $this->batch->completed_at = $this->now->copy()->addMinutes(30);
  $this->batch->created_at = $this->now;
  $this->batch->updated_at = $this->now;
});

it('transforms the resource attributes correctly into array', function () {
  // تجهيز الـ Request الوهمي الخاص بـ Laravel لتقديمه للـ Resource
  $request = Request::create('/api/batches', 'GET');

  $resource = new NotificationBatchResource($this->batch);
  $result = $resource->toArray($request);

  // التحقق من صحة نقل كافة البيانات والـ ISO Formatting للتواريخ
  expect($result)->toBeArray()
    ->and($result['id'])->toBe('batch-123')
    ->and($result['project_id'])->toBe('project-xyz')
    ->and($result['created_by_type'])->toBe('service')
    ->and($result['created_by_id'])->toBe('service-admin')
    ->and($result['source_service'])->toBe('marketing_engine')
    ->and($result['source_event_type'])->toBe('campaign.broadcast')
    ->and($result['audience_type'])->toBe('segment')
    ->and($result['audience_query'])->toBe(['country' => 'SA', 'active' => true])
    ->and($result['status'])->toBe('processing')
    ->and($result['dedupe_key'])->toBe('dedupe_campaign_55')
    ->and($result['total_targets'])->toBe(1500)
    ->and($result['processed_targets'])->toBe(750)
    ->and($result['scheduled_at'])->toBe($this->batch->scheduled_at->toISOString())
    ->and($result['started_at'])->toBe($this->batch->started_at->toISOString())
    ->and($result['completed_at'])->toBe($this->batch->completed_at->toISOString())
    ->and($result['created_at'])->toBe($this->batch->created_at->toISOString())
    ->and($result['updated_at'])->toBe($this->batch->updated_at->toISOString());
});

it('handles optional nullable dates correctly', function () {
  // تعيين كافة التواريخ الاختيارية إلى null لاختبار الـ optional() Helper المكتوب لديك
  $this->batch->scheduled_at = null;
  $this->batch->started_at = null;
  $this->batch->completed_at = null;
  $this->batch->created_at = null;
  $this->batch->updated_at = null;

  $request = Request::create('/api/batches', 'GET');
  $resource = new NotificationBatchResource($this->batch);
  $result = $resource->toArray($request);

  // التأكد من عدم انهيار الكود ورجوع القيم بـ null بأمان
  expect($result['scheduled_at'])->toBeNull()
    ->and($result['started_at'])->toBeNull()
    ->and($result['completed_at'])->toBeNull()
    ->and($result['created_at'])->toBeNull()
    ->and($result['updated_at'])->toBeNull();
});
