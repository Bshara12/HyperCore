<?php

namespace App\Services\MessageBroker;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    /*
     | Exchange مشترك بين كل الخدمات من نوع "topic"
     | topic يتيح التوجيه بناءً على Routing Key مثل: auth.user.registered
     | كل خدمة تستمع فقط للـ keys التي تهمها
     */
    private const EXCHANGE      = 'microservices';
    private const EXCHANGE_TYPE = 'topic';

    /**
     * نشر حدث على RabbitMQ
     *
     * @param string $routingKey  مسار الحدث مثل: auth.user.registered
     * @param array  $payload     البيانات المرسلة مع الحدث
     */
    public function publish(string $routingKey, array $payload): void
    {
        $connection = null;
        $channel    = null;

        try {
            // ─── 1. فتح الاتصال ───────────────────────────────────────────
            // ✅ الصحيح — استخدم config() دائماً
            $connection = new AMQPStreamConnection(
                host:     config('queue.connections.rabbitmq.host'),
                port:     config('queue.connections.rabbitmq.port'),
                user:     config('queue.connections.rabbitmq.login'),
                password: config('queue.connections.rabbitmq.password'),
                vhost:    config('queue.connections.rabbitmq.vhost'),
            );
            // $connection = new AMQPStreamConnection(
            //     host:     config('rabbitmq.host'),
            //     port:     config('rabbitmq.port'),
            //     user:     config('rabbitmq.user'),
            //     password: config('rabbitmq.password'),
            //     vhost:    config('rabbitmq.vhost'),
            // );

            $channel = $connection->channel();

            // ─── 2. التأكد من وجود الـ Exchange ──────────────────────────
            // durable: true = يبقى الـ Exchange حتى بعد إعادة تشغيل RabbitMQ
            $channel->exchange_declare(
                exchange:    self::EXCHANGE,
                type:        self::EXCHANGE_TYPE,
                passive:     false,
                durable:     true,
                auto_delete: false,
            );

            // ─── 3. بناء الرسالة ──────────────────────────────────────────
            $message = new AMQPMessage(
                body: json_encode([
                    ...$payload,
                    // نضيف metadata مفيدة للـ Debugging والـ Tracing
                    '_meta' => [
                        'source'     => 'auth-service',
                        'event'      => $routingKey,
                        'published_at' => now()->toIso8601String(),
                    ],
                ]),
                properties: [
                    'content_type'  => 'application/json',
                    // PERSISTENT: الرسالة تبقى حتى لو أُعيد تشغيل RabbitMQ
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ],
            );

            // ─── 4. نشر الرسالة ───────────────────────────────────────────
            $channel->basic_publish(
                msg:         $message,
                exchange:    self::EXCHANGE,
                routing_key: $routingKey,
            );

            Log::info('[RabbitMQPublisher] Event published', [
                'routing_key' => $routingKey,
                'user_id'     => $payload['user_id'] ?? null,
            ]);

        } catch (\Exception $e) {
            /*
             | نسجّل الخطأ لكن لا نوقف العملية الأصلية (التسجيل، OTP...)
             | فشل إرسال الإشعار لا يجب أن يمنع المستخدم من التسجيل
             */
            Log::error('[RabbitMQPublisher] Failed to publish event', [
                'routing_key' => $routingKey,
                'error'       => $e->getMessage(),
            ]);

        } finally {
            // ─── 5. إغلاق الاتصال دائماً حتى عند الخطأ ──────────────────
            $channel?->close();
            $connection?->close();
        }
    }
}
