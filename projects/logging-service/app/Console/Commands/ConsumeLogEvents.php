<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeLogEvents extends Command
{
  protected $signature = 'consume:logs';

  protected $description = 'Consume log events from RabbitMQ';

  public function handle(): int
  {
    /** @var string $rabbitmqHost */
    $rabbitmqHost = config('rabbitmq.host');

    /** @var string $rabbitmqPort */
    $rabbitmqPort = config('rabbitmq.port');

    /** @var string $rabbitmqUser */
    $rabbitmqUser = config('rabbitmq.user');

    /** @var string $rabbitmqPassword */
    $rabbitmqPassword = config('rabbitmq.password');


    $connection = new AMQPStreamConnection(
      $rabbitmqHost,
      $rabbitmqPort,
      $rabbitmqUser,
      $rabbitmqPassword,
      '/',
      false,
      'AMQPLAIN',
      null,
      'en_US',
      3,
      120,
      null,
      false,
      60
    );

    $channel = $connection->channel();
    $channel->queue_declare('logs_queue', false, true, false, false);
    // $channel->basic_qos(null, 1, null);
    $channel->basic_qos(0, 1, false);

    $channel->basic_consume(
      'logs_queue',
      '',
      false,
      false,
      false,
      false,
      function (AMQPMessage $msg) use ($channel): void {
        /** @var array<string, mixed>|null $data */

        $data = json_decode($msg->body, true);

        if (! $data) {
          echo "Invalid message\n";
          // ازا ما زبط شيل نوع المسج
          // $msg->ack();

          /** @var string $deliveryTag */
          $deliveryTag = $msg->delivery_info['delivery_tag'];

          $channel->basic_ack($deliveryTag);
          return;
        }

        try {

          if (($data['event_type'] ?? null) === 'audit') {

            $inserted = DB::table('audit_logs')->insertOrIgnore([
              'event_id' => $data['event_id'],
              'module' => $data['module'],
              'entity_type' => $data['entity_type'] ?? null,
              'entity_id' => $data['entity_id'] ?? null,
              'old_values' => isset($data['old_values']) ? json_encode($data['old_values']) : null,
              'new_values' => isset($data['new_values']) ? json_encode($data['new_values']) : null,
              'user_id' => $data['user_id'] ?? null,
              'correlation_id' => $data['correlation_id'] ?? null,
              'occurred_at' => $data['occurred_at'],
              'created_at' => now(),
              'updated_at' => now(),
            ]);

            if ($inserted) {
              echo "Audit log saved\n";
            } else {
              echo "Duplicate audit event skipped\n";
            }
          } else {

            $inserted = DB::table('logs')->insertOrIgnore([
              'event_id' => $data['event_id'],
              'module' => $data['module'],
              'event_type' => $data['event_type'],
              'user_id' => $data['user_id'] ?? null,
              'entity_type' => $data['entity_type'] ?? null,
              'entity_id' => $data['entity_id'] ?? null,
              'project_id' => $data['project_id'] ?? null,
              'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
              'correlation_id' => $data['correlation_id'] ?? null,
              'occurred_at' => $data['occurred_at'],
              'created_at' => now(),
              'updated_at' => now(),
            ]);

            if ($inserted) {
              echo "Log saved\n";
            } else {
              echo "Duplicate log skipped\n";
            }
          }
        } catch (\Throwable $e) {

          echo 'Error processing message: ' . $e->getMessage() . "\n";
        }

        // $channel->basic_ack($msg->delivery_info['delivery_tag']);

        /** @var string $deliveryTag */
        $deliveryTag = $msg->delivery_info['delivery_tag'];

        $channel->basic_ack($deliveryTag);
      }
    );

    echo "Waiting for messages...\n";

    // while (true) {
    //   $channel->wait();
    // }
    for (;;) {
      $channel->wait();
    }
  }
}
