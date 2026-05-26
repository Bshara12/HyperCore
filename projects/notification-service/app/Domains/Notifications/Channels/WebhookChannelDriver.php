<?php

namespace App\Domains\Notifications\Channels;

use App\Domains\Notifications\Contracts\NotificationChannelDriver;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookChannelDriver implements NotificationChannelDriver
{
  public function __construct(
    private readonly NotificationDeliveryService $deliveryService
  ) {}

  public function send(NotificationDelivery $delivery): void
  {
    try {
      // 1. التحقق من العلاقات والبيانات (الـ Skips والـ Fails الأولية)
      if (! $delivery->notification) {
        $this->deliveryService->markFailed($delivery, 'notification_missing', 'Notification relation is missing.');
        return;
      }

      $url = $delivery->notification->metadata['webhook']['url'] ?? config('services.notification_webhook.url');
      if (! $url) {
        $this->deliveryService->markSkipped($delivery, 'Webhook URL is missing.');
        return;
      }

      // 2. تحديث الحالة إلى الكيو وتجهيز البيانات
      $this->deliveryService->markQueued($delivery);
      $this->deliveryService->markSent($delivery);

      $payload = [
        'notification_id' => $delivery->notification->id,
        'project_id' => $delivery->notification->project_id,
        'recipient_type' => $delivery->notification->recipient_type,
        'recipient_id' => $delivery->notification->recipient_id,
        'title' => $delivery->notification->title,
        'body' => $delivery->notification->body,
        'data' => $delivery->notification->data,
      ];

      $secret = $delivery->notification->metadata['webhook']['secret'] ?? config('services.notification_webhook.secret');
      $headers = $delivery->notification->metadata['webhook']['headers'] ?? [];

      if ($secret) {
        $headers['X-Webhook-Signature'] = hash_hmac('sha256', json_encode($payload), $secret);
      }

      // 3. إرسال الطلب مع الـ Retry والـ Timeout
      // عند الفشل، سيقوم الـ retry تلقائياً برمي استثناء وينتقل فوراً للـ catch بالأسفل
      $response = Http::acceptJson()
        ->timeout(10)
        ->retry(3, 300)
        ->withHeaders($headers)
        ->post($url, $payload);

      // تم حذف الـ if ($response->failed()) الميتة من هنا بنجاح

      $this->deliveryService->markDelivered($delivery);
    } catch (\Throwable $e) {
      // الـ catch تلتقط الـ RequestException وكل أنواع الفشل هنا وتخزنها بشكل مثالي
      $this->deliveryService->markFailed(
        delivery: $delivery,
        code: class_basename($e),
        message: substr($e->getMessage(), 0, 500),
        backoffMinutes: 5 * max(1, $delivery->attempts + 1)
      );

      throw $e;
    }
  }

  // public function send(NotificationDelivery $delivery): void
  // {
  //   $notification = $delivery->notification;

  //   if (! $notification) {
  //     $this->deliveryService->markFailed(
  //       delivery: $delivery,
  //       code: 'notification_missing',
  //       message: 'Notification relation is missing.'
  //     );

  //     return;
  //   }

  //   $webhookUrl = data_get(
  //     $notification->metadata,
  //     'webhook.url',
  //     config('services.notification_webhook.url')
  //   );

  //   if (! $webhookUrl) {
  //     $this->deliveryService->markSkipped($delivery, 'Webhook URL is missing.');

  //     return;
  //   }

  //   $headers = data_get(
  //     $notification->metadata,
  //     'webhook.headers',
  //     config('services.notification_webhook.headers', [])
  //   );

  //   $secret = data_get(
  //     $notification->metadata,
  //     'webhook.secret',
  //     config('services.notification_webhook.secret')
  //   );

  //   $payload = [
  //     'notification_id' => $notification->id,
  //     'project_id' => $notification->project_id,
  //     'recipient' => [
  //       'type' => $notification->recipient_type,
  //       'id' => $notification->recipient_id,
  //     ],
  //     'source' => [
  //       'type' => $notification->source_type,
  //       'service' => $notification->source_service,
  //       'id' => $notification->source_id,
  //     ],
  //     'title' => $notification->title,
  //     'body' => $notification->body,
  //     'data' => $notification->data,
  //     'metadata' => $notification->metadata,
  //     'created_at' => optional($notification->created_at)?->toISOString(),
  //   ];

  //   try {
  //     $this->deliveryService->markQueued($delivery);

  //     $response = Http::acceptJson()
  //       ->timeout(10)
  //       ->retry(3, 300)
  //       ->withHeaders($headers)
  //       ->when($secret, function ($request) use ($payload, $secret) {
  //         $signature = hash_hmac('sha256', json_encode($payload), $secret);

  //         return $request->withHeaders([
  //           'X-Webhook-Signature' => $signature,
  //         ]);
  //       })
  //       ->post($webhookUrl, $payload);

  //     if ($response->failed()) {
  //       $this->deliveryService->markFailed(
  //         delivery: $delivery,
  //         code: (string) $response->status(),
  //         message: substr((string) $response->body(), 0, 500),
  //         backoffMinutes: 5 * max(1, $delivery->attempts + 1)
  //       );

  //       throw new \RuntimeException('Webhook delivery failed.');
  //     }

  //     $this->deliveryService->markSent($delivery);
  //     $this->deliveryService->markDelivered($delivery);
  //   } catch (Throwable $e) {
  //     $this->deliveryService->markFailed(
  //       delivery: $delivery,
  //       code: class_basename($e),
  //       message: $e->getMessage(),
  //       backoffMinutes: 5 * max(1, $delivery->attempts + 1)
  //     );

  //     throw $e;
  //   }
  // }
}
