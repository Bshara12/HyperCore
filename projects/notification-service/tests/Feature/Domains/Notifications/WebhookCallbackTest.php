<?php

namespace Tests\Feature\Domains\Notifications;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_delivery_from_valid_webhook_callback(): void
    {
        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'ops',
            'title' => 'Webhook test',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
            'metadata' => [
                'webhook' => [
                    'secret' => 'super-secret-key',
                ],
            ],
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'webhook',
            'status' => DeliveryStatus::Queued,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload_snapshot' => [],
        ]);

        $payload = [
            'delivery_id' => $delivery->id,
            'provider_message_id' => 'msg_123',
            'status' => 'delivered',
            'error_code' => null,
            'error_message' => null,
            'payload' => [
                'external_id' => 'ext-1',
            ],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $rawBody, 'super-secret-key');

        $response = $this->withHeaders([
            'X-Webhook-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/api/v1/internal/deliveries/webhook/callback', $payload);

        $response->assertOk();

        $this->assertSame(DeliveryStatus::Delivered->value, $delivery->fresh()->status->value);
        $this->assertSame('msg_123', $delivery->fresh()->provider_message_id);
    }

    public function test_it_rejects_invalid_signature(): void
    {
        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'ops',
            'title' => 'Webhook test',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
            'metadata' => [
                'webhook' => [
                    'secret' => 'super-secret-key',
                ],
            ],
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'webhook',
            'status' => DeliveryStatus::Queued,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload_snapshot' => [],
        ]);

        $payload = [
            'delivery_id' => $delivery->id,
            'provider_message_id' => 'msg_123',
            'status' => 'delivered',
        ];

        $response = $this->withHeaders([
            'X-Webhook-Signature' => 'invalid-signature',
            'Content-Type' => 'application/json',
        ])->post('/api/v1/internal/deliveries/webhook/callback', $payload);

        $response->assertStatus(401);
    }

    public function test_it_returns_404_when_delivery_not_found(): void
    {
        $payload = [
            'delivery_id' => 'non-existing',
            'provider_message_id' => 'msg_123',
            'status' => 'delivered',
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $rawBody, 'super-secret-key');

        $response = $this->withHeaders([
            'X-Webhook-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/api/v1/internal/deliveries/webhook/callback', $payload);

        $response->assertStatus(404);
    }
}
