<?php

namespace Tests\Unit\Services\MessageBroker;

use App\Services\MessageBroker\RabbitMQPublisher;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RabbitMQPublisherTest extends TestCase
{
  #[Test]
  public function it_publishes_event_to_rabbitmq_successfully()
  {
    Log::spy();

    // 1. إعداد Mock للقناة (Channel)
    $channelMock = Mockery::mock('PhpAmqpLib\Channel\AMQPChannel');

    $channelMock->shouldReceive('exchange_declare')
      ->once()
      ->with('microservices', 'topic', false, true, false);

    $channelMock->shouldReceive('basic_publish')
      ->once()
      ->with(
        Mockery::on(function ($msg) {
          $body = json_decode($msg->getBody(), true);

          return $body['booking_id'] === 10
            && $body['user_id'] === 5
            && $body['_meta']['source'] === 'booking-service'
            && $body['_meta']['event'] === 'booking.created'
            && isset($body['_meta']['published_at']);
        }),
        'microservices',
        'booking.created'
      );

    $channelMock->shouldReceive('close')->once();

    // 2. إعداد Mock للاتصال (Connection)
    $connectionMock = Mockery::mock('PhpAmqpLib\Connection\AMQPStreamConnection');
    $connectionMock->shouldReceive('channel')->once()->andReturn($channelMock);
    $connectionMock->shouldReceive('close')->once();

    // 3. عمل Partial Mock للكلاس واعتراض دالة createConnection فقط
    $publisher = Mockery::mock(RabbitMQPublisher::class)->makePartial();
    $publisher->shouldAllowMockingProtectedMethods();
    $publisher->shouldReceive('createConnection')
      ->once()
      ->andReturn($connectionMock);

    // 4. التنفيذ
    $publisher->publish('booking.created', [
      'booking_id' => 10,
      'user_id'    => 5,
    ]);

    // 5. التأكد من تسجيل الـ Log
    Log::shouldHaveReceived('info')
      ->once()
      ->with(
        '[RabbitMQPublisher] Event published',
        Mockery::on(function ($context) {
          return $context['routing_key'] === 'booking.created'
            && $context['booking_id'] === 10
            && $context['user_id'] === 5;
        })
      );
  }

  #[Test]
  public function it_handles_connection_exceptions_gracefully_without_crashing_the_app()
  {
    Log::spy();

    // محاكاة حدوث استثناء أثناء الاتصال
    $publisher = Mockery::mock(RabbitMQPublisher::class)->makePartial();
    $publisher->shouldAllowMockingProtectedMethods();
    $publisher->shouldReceive('createConnection')
      ->once()
      ->andThrow(new \Exception('RabbitMQ Connection Refused'));

    // التنفيذ
    $publisher->publish('booking.created', ['booking_id' => 10]);

    // التأكد من تسجيل الخطأ
    Log::shouldHaveReceived('error')
      ->once()
      ->with(
        '[RabbitMQPublisher] Failed to publish',
        Mockery::on(function ($context) {
          return $context['routing_key'] === 'booking.created'
            && $context['error'] === 'RabbitMQ Connection Refused';
        })
      );
  }
}
