<?php

namespace App\Console\Commands;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsumeLogEvents extends Command
{
  protected $signature = 'consume:logs';
  protected $description = 'Consume log events from RabbitMQ';

  public function handle()
  {
    $connection = new AMQPStreamConnection(
      env('RABBITMQ_HOST'),
      env('RABBITMQ_PORT'),
      env('RABBITMQ_USER'),
      env('RABBITMQ_PASSWORD'),
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
    $channel->basic_qos(null, 1, null);
    $channel->basic_qos(0, 1, false);

    $channel->basic_consume(
      'logs_queue',
      '',
      false,
      false,
      false,
      false,
      function ($msg) use ($channel) {

        $data = json_decode($msg->body, true);

        if (!$data) {
          echo "Invalid message\n";
          $msg->ack();
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

          echo "Error processing message: " . $e->getMessage() . "\n";
        }

        $channel->basic_ack($msg->delivery_info['delivery_tag']);
      }
    );

    echo "Waiting for messages...\n";

    while (true) {
      $channel->wait();
    }
  }
}
