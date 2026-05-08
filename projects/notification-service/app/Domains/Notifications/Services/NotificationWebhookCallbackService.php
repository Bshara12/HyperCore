<?php

namespace App\Domains\Notifications\Services;

use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotificationWebhookCallbackService
{
    public function __construct(
        private readonly NotificationDeliveryService $deliveryService
    ) {}

    public function handle(Request $request, array $data): NotificationDelivery
    {
        $delivery = NotificationDelivery::query()
            ->with('notification')
            ->where('id', $data['delivery_id'])
            ->first();

        if (! $delivery) {
            abort(404, 'Delivery not found.');
        }

        $this->verifySignature($request, $delivery);

        if (! empty($data['provider_message_id'])) {
            $delivery->forceFill([
                'provider' => 'webhook',
                'provider_message_id' => $data['provider_message_id'],
            ])->save();
        }

        $status = strtolower($data['status']);

        return match ($status) {
            'delivered', 'processed' => $this->markDelivered($delivery),
            'failed' => $this->markFailed($delivery, $data),
            'sent', 'received' => $this->markSent($delivery),
            'skipped' => $this->markSkipped($delivery),
            default => $delivery,
        };
    }

    private function verifySignature(Request $request, NotificationDelivery $delivery): void
    {
        $secret = data_get($delivery->notification?->metadata, 'webhook.secret');

        if (! $secret) {
            // لو لم يُعرّف secret فلا نمنع الكولباك، لكن الأفضل في الإنتاج تعريفه.
            return;
        }

        $signature = (string) $request->header('X-Webhook-Signature', '');
        $rawBody = $request->getContent();

        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new HttpException(401, 'Invalid webhook signature.');
        }
    }

    private function markDelivered(NotificationDelivery $delivery): NotificationDelivery
    {
        $this->deliveryService->markDelivered($delivery);

        return $delivery->fresh();
    }

    private function markSent(NotificationDelivery $delivery): NotificationDelivery
    {
        $this->deliveryService->markSent($delivery);

        return $delivery->fresh();
    }

    private function markSkipped(NotificationDelivery $delivery): NotificationDelivery
    {
        $this->deliveryService->markSkipped($delivery, 'Webhook callback marked as skipped.');

        return $delivery->fresh();
    }

    private function markFailed(NotificationDelivery $delivery, array $data): NotificationDelivery
    {
        $this->deliveryService->markFailed(
            delivery: $delivery,
            code: $data['error_code'] ?? 'WEBHOOK_FAILED',
            message: $data['error_message'] ?? 'Webhook callback reported failure.',
            backoffMinutes: 5 * max(1, $delivery->attempts + 1)
        );

        return $delivery->fresh();
    }
}
