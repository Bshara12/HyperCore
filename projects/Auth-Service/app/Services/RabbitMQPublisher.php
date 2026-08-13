<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
  public function publish(array $data)
  {
    // الاعتماد على الـ Service Container لإنشاء الكائن بدلاً من الـ new الصريحة
    $connection = app(AMQPStreamConnection::class, [
      'host'     => config('services.rabbitmq.host'),
      'port'     => config('services.rabbitmq.port'),
      'user'     => config('services.rabbitmq.user'),
      'password' => config('services.rabbitmq.password')
    ]);

    $channel = $connection->channel();

    $channel->queue_declare('logs_queue', false, true, false, false);

    $msg = new AMQPMessage(
      json_encode($data),
      ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
    );

    $channel->basic_publish($msg, '', 'logs_queue');

    $channel->close();
    $connection->close();
  }
}

// // audit
// event(new SystemLogEvent(
//     module: 'cms',
//     eventType: 'audit',
//     userId: auth()->id(),
//     entityType: 'project',
//     entityId: $project->id,
//     oldValues: $oldData,
//     newValues: $project->toArray()
// ));

// // log
// event(new SystemLogEvent(
//     module: 'cms',
//     eventType: 'project_created',
//     userId: auth()->id(),
//     entityType: 'project',
//     entityId: $project->id
// ));
