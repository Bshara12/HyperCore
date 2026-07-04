<?php

namespace App\Services\MessageBroker;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private const EXCHANGE      = 'microservices';
    private const EXCHANGE_TYPE = 'topic';

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
            // $connection = new AMQPStreamConnection(
            //     host:     config('rabbitmq.host'),
            //     port:     config('rabbitmq.port'),
            //     user:     config('rabbitmq.user'),
            //     password: config('rabbitmq.password'),
            //     vhost:    config('rabbitmq.vhost'),
            // );

            $channel = $connection->channel();

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
                        'source'       => 'cms-service',
                        'event'        => $routingKey,
                        'published_at' => now()->toIso8601String(),
                    ],
                ]),
                properties: [
                    'content_type'  => 'application/json',
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
                'user_id'     => $payload['user_id'] ?? null,
            ]);

        } catch (\Exception $e) {
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
