<?php

namespace Tests\Unit\Domains\Core\Services;

use App\Domains\Core\Services\RabbitMQPublisher;
use Mockery;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Config;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */

// لتجنب تعارض الـ Mocks مع اختبارات أخرى
afterEach(function () {
  Mockery::close();
});

it('publishes a message to rabbitmq successfully', function () {
  // 1. إعداد البيانات الوهمية للإعدادات
  Config::set('services.rabbitmq.host', 'localhost');
  Config::set('services.rabbitmq.port', 5672);
  Config::set('services.rabbitmq.user', 'guest');
  Config::set('services.rabbitmq.password', 'guest');

  $testData = ['event' => 'project_created', 'id' => 1];

  // 2. عمل Mock للـ Channel
  $mockChannel = Mockery::mock(AMQPChannel::class);

  // توقع إعلان الطابور (Queue Declare)
  $mockChannel->shouldReceive('queue_declare')
    ->once()
    ->with('logs_queue', false, true, false, false);

  // توقع نشر الرسالة (Basic Publish)
  $mockChannel->shouldReceive('basic_publish')
    ->once()
    ->with(Mockery::type(AMQPMessage::class), '', 'logs_queue');

  // توقع إغلاق القناة
  $mockChannel->shouldReceive('close')->once();

  // 3. عمل Mock للاتصال (Connection)
  // ملاحظة: بما أن الاتصال يتم عبر 'new'، سنحتاج لمحاكاة الكلاس نفسه
  // إذا كنت تستخدم Laravel، يفضل جعل الـ Connection يُحقن عبر المنشئ (Constructor)
  // ولكن كحل لهذا الكود، سنستخدم "Overload" في Mockery:
  $mockConnection = Mockery::mock('overload:' . AMQPStreamConnection::class);
  $mockConnection->shouldReceive('channel')->once()->andReturn($mockChannel);
  $mockConnection->shouldReceive('close')->once();

  // 4. تنفيذ العملية
  $publisher = new RabbitMQPublisher();
  $publisher->publish($testData);

  // التحقق من صحة التوقعات
  expect(true)->toBeTrue();
});
