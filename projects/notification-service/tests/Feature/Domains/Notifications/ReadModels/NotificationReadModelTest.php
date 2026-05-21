<?php

use App\Domains\Notifications\ReadModels\NotificationReadModel;
use App\Models\Domains\Notifications\Models\Notification;
use App\Domains\Notifications\Enums\NotificationStatus;
use Carbon\Carbon;

beforeEach(function () {
  // 💡 تثبيت الوقت عند بداية الثانية لتجنب مشاكل الـ Microseconds أثناء الـ JSON Serialization
  $this->now = Carbon::now()->startOfSecond();

  // جلب الـ Enum المتاح ديناميكياً لتجنب الـ ValueError
  $statusEnum = null;
  if (defined(NotificationStatus::class . '::SENT')) {
    $statusEnum = constant(NotificationStatus::class . '::SENT');
  } elseif (method_exists(NotificationStatus::class, 'cases')) {
    $cases = NotificationStatus::cases();
    $statusEnum = $cases[0] ?? null;
  }

  $this->notification = (new Notification())->forceFill([
    'id' => 'notif-123',
    'project_id' => 'project-abc',
    'recipient_type' => 'user',
    'recipient_id' => 456,
    'title' => 'تنبيه جديد',
    'body' => 'محتوى الإشعار للتجربة',
    'priority' => 1,
    'topic_key' => 'billing.success',
    'data' => ['amount' => 100],
    'metadata' => ['browser' => 'Chrome'],
    'source_type' => 'system',
    'source_service' => 'payment_gateway',
    'source_id' => 'src-999',
  ]);

  $this->notification->read_at = $this->now;
  $this->notification->created_at = $this->now;
  $this->notification->updated_at = $this->now;
  $this->notification->status = $statusEnum;
});

it('can create a read model from notification model', function () {
  $readModel = NotificationReadModel::fromNotification($this->notification);

  expect($readModel->id)->toBe('notif-123')
    ->and($readModel->projectId)->toBe('project-abc')
    ->and($readModel->recipientType)->toBe('user')
    ->and($readModel->recipientId)->toBe(456)
    ->and($readModel->title)->toBe('تنبيه جديد')
    ->and($readModel->body)->toBe('محتوى الإشعار للتجربة')
    // 💡 المقارنة بالقيمة الفعلية للـ Enum المستخرج لتفادي التضارب
    ->and($readModel->status)->toBe($this->notification->status->value)
    ->and($readModel->priority)->toBe(1)
    ->and($readModel->topicKey)->toBe('billing.success')
    ->and($readModel->data)->toBe(['amount' => 100])
    ->and($readModel->metadata)->toBe(['browser' => 'Chrome'])
    ->and($readModel->source)->toBe([
      'type' => 'system',
      'service' => 'payment_gateway',
      'id' => 'src-999',
    ])
    ->and($readModel->readAt)->toBe($this->now->toISOString())
    ->and($readModel->createdAt)->toBe($this->now->toISOString())
    ->and($readModel->updatedAt)->toBe($this->now->toISOString());
});

it('handles null project id correctly', function () {
  $this->notification->project_id = null;

  $readModel = NotificationReadModel::fromNotification($this->notification);

  expect($readModel->projectId)->toBeNull();
});

it('can transform into an array structure', function () {
  $readModel = NotificationReadModel::fromNotification($this->notification);
  $arrayData = $readModel->toArray();

  expect($arrayData)->toBeArray()
    ->and($arrayData)->toHaveKey('id')
    ->and($arrayData['project_id'])->toBe('project-abc')
    ->and($arrayData['recipient_type'])->toBe('user')
    // 💡 المقارنة بالقيمة الفعلية للـ Enum هنا أيضاً
    ->and($arrayData['status'])->toBe($this->notification->status->value)
    ->and($arrayData['source'])->toBe([
      'type' => 'system',
      'service' => 'payment_gateway',
      'id' => 'src-999',
    ]);
});

it('implements jsonserializable interface correctly', function () {
  $readModel = NotificationReadModel::fromNotification($this->notification);

  $json = json_encode($readModel);
  $decoded = json_decode($json, true);

  expect($decoded)->toBeArray()
    ->and($decoded['id'])->toBe('notif-123')
    ->and($decoded['recipient_id'])->toBe(456)
    ->and($decoded['created_at'])->toBe($this->now->toISOString());
});
