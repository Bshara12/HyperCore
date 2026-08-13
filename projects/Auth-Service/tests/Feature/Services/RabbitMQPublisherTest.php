<?php

namespace Tests\Feature\Services;

use App\Services\RabbitMQPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Mockery;

beforeEach(function () {
  config([
    'services.rabbitmq.host'     => '127.0.0.1',
    'services.rabbitmq.port'     => 5672,
    'services.rabbitmq.user'     => 'guest',
    'services.rabbitmq.password' => 'guest',
  ]);
});

test('publish method opens connection, declares queue, publishes message, and closes safely', function () {
  // 1. إنشاء الـ Mocks بشكل مستقل تماماً
  $connectionMock = mock(AMQPStreamConnection::class);
  $channelMock    = mock(AMQPChannel::class);

  // 2. اعتراض الحاوي واجبار لارافل على إعادة الـ Mock حتى لو تم تمرير باراميترات في الـ app()
  app()->bind(AMQPStreamConnection::class, function () use ($connectionMock) {
    return $connectionMock;
  });

  // 3. رسم توقعات الـ Connection
  $connectionMock->shouldReceive('channel')
    ->once()
    ->andReturn($channelMock);

  $connectionMock->shouldReceive('close')
    ->once();

  // 4. رسم توقعات الـ Channel
  $channelMock->shouldReceive('queue_declare')
    ->once()
    ->with('logs_queue', false, true, false, false);

  $channelMock->shouldReceive('basic_publish')
    ->once()
    ->with(Mockery::on(function ($msg) {
      if (!$msg instanceof AMQPMessage) {
        return false;
      }
      $payload = json_decode($msg->body, true);
      return $payload['module'] === 'cms' && $payload['eventType'] === 'audit';
    }), '', 'logs_queue');

  $channelMock->shouldReceive('close')
    ->once();

  // 5. تشغيل الخدمة بأمان
  $publisher = new RabbitMQPublisher();
  $publisher->publish([
    'module'    => 'cms',
    'eventType' => 'audit'
  ]);

  expect(true)->toBeTrue();
});
