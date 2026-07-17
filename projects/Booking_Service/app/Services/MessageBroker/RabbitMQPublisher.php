<?php

namespace App\Services\MessageBroker;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private const EXCHANGE      = 'microservices';
    private const EXCHANGE_TYPE = 'topic';

    /**
     * نشر حدث على RabbitMQ
     *
     * @param string $routingKey  مسار الحدث: booking.booking.created
     * @param array  $payload     بيانات الحدث
     */
    public function publish(string $routingKey, array $payload): void
    {
        $connection = null;
        $channel    = null;

        try {
            // ✅ الصحيح — استخدم config() دائماً
            $connection = new AMQPStreamConnection(
                host:     config('queue.connections.rabbitmq.host'),
                port:     config('queue.connections.rabbitmq.port'),
                user:     config('queue.connections.rabbitmq.login'),
                password: config('queue.connections.rabbitmq.password'),
                vhost:    config('queue.connections.rabbitmq.vhost'),
            );

            $channel = $connection->channel();

            // التأكد من وجود الـ Exchange قبل النشر
            $channel->exchange_declare(
                exchange:    self::EXCHANGE,
                type:        self::EXCHANGE_TYPE,
                passive:     false,
                durable:     true,
                auto_delete: false,
            );

            $message = new AMQPMessage(
                body: json_encode([
                    ...$payload,
                    '_meta' => [
                        'source'       => 'booking-service',
                        'event'        => $routingKey,
                        'published_at' => now()->toIso8601String(),
                    ],
                ]),
                properties: [
                    'content_type'  => 'application/json',
                    // PERSISTENT: الرسالة لا تضيع عند إعادة تشغيل RabbitMQ
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ],
            );

            $channel->basic_publish(
                msg:         $message,
                exchange:    self::EXCHANGE,
                routing_key: $routingKey,
            );

            Log::info('[RabbitMQPublisher] Event published', [
                'routing_key' => $routingKey,
                'booking_id'  => $payload['booking_id'] ?? null,
                'user_id'     => $payload['user_id'] ?? null,
            ]);

        } catch (\Exception $e) {
            // فشل النشر لا يوقف العملية الأصلية (إنشاء الحجز، الإلغاء...)
            Log::error('[RabbitMQPublisher] Failed to publish', [
                'routing_key' => $routingKey,
                'error'       => $e->getMessage(),
            ]);

        } finally {
            $channel?->close();
            $connection?->close();
        }
    }
}
