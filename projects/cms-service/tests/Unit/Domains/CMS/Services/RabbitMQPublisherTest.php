<?php

use App\Domains\CMS\Services\RabbitMQPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

test('it publishes message to rabbitmq queue successfully', function () {
  // 1. Arrange: إنشاء Mocks للسلسلة (Connection -> Channel)
  $channelMock = Mockery::mock(AMQPChannel::class);
  $connectionMock = Mockery::mock(AMQPStreamConnection::class);

  // 2. Expectations: ضبط التوقعات لما سيحدث داخل الخدمة

  // يجب أن يتم استدعاء channel() من الاتصال
  $connectionMock->shouldReceive('channel')
    ->once()
    ->andReturn($channelMock);

  // يجب تعريف الـ Queue بالمواصفات الصحيحة
  $channelMock->shouldReceive('queue_declare')
    ->once()
    ->with('logs_queue', false, true, false, false);

  // يجب إرسال الرسالة بنجاح
  $channelMock->shouldReceive('basic_publish')
    ->once()
    ->with(Mockery::type(AMQPMessage::class), '', 'logs_queue');

  // يجب إغلاق القناة بعد الإرسال
  $channelMock->shouldReceive('close')
    ->once();

  // 3. Act: حقن الـ Mock في الخدمة وتنفيذ العملية
  $publisher = new RabbitMQPublisher($connectionMock);
  $publisher->publish(['test' => 'data']);

  // 4. Assert: إذا وصل الكود هنا بدون خطأ Mockery، فالاختبار ناجح
  expect(true)->toBeTrue();
});
