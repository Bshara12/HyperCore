<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create a message and cast attributes correctly', function () {
  // 1. نحتاج محادثة أولاً لأنها علاقة أجنبية
  $conversation = AiConversation::create([
    'user_id' => 1,
    'status' => 'active'
  ]);

  // 2. إنشاء رسالة مع بيانات JSON و Boolean
  $message = AiMessage::create([
    'conversation_id' => $conversation->id,
    'role' => 'assistant',
    'content' => 'Hello!',
    'schema' => ['key' => 'value'], // سيتم تحويله لـ array
    'is_provisioned' => 1,          // سيتم تحويله لـ boolean (true)
    'sequence' => 1,
  ]);

  // 3. التأكد من الـ Casting
  expect($message->schema)->toBeArray()
    ->and($message->schema['key'])->toBe('value')
    ->and($message->is_provisioned)->toBeTrue();
});

test('it belongs to a conversation', function () {
  $conversation = AiConversation::create(['user_id' => 1, 'status' => 'active']);
  $message = AiMessage::create([
    'conversation_id' => $conversation->id,
    'role' => 'user',
    'content' => 'Hi',
    'sequence' => 1
  ]);

  expect($message->conversation->id)->toBe($conversation->id);
});
