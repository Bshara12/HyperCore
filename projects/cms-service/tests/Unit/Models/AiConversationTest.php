<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can be created with valid attributes', function () {
  $conversation = AiConversation::create([
    'user_id' => 1,
    'title' => 'Test Conversation',
    'provisioned_project_id' => 100,
    'status' => 'active',
  ]);

  expect($conversation->title)->toBe('Test Conversation')
    ->and($conversation->provisioned_project_id)->toBe(100)
    ->and($conversation->status)->toBe('active');
});

test('it has many messages ordered by sequence', function () {
  $conversation = AiConversation::create([
    'user_id' => 1,
    'title' => 'Test',
    'provisioned_project_id' => 1,
    'status' => 'active',
  ]);

  // إضافة 'role' لكل رسالة
  AiMessage::create([
    'conversation_id' => $conversation->id,
    'sequence' => 2,
    'content' => 'Message 2',
    'role' => 'user' // <--- الإضافة المطلوبة
  ]);

  AiMessage::create([
    'conversation_id' => $conversation->id,
    'sequence' => 1,
    'content' => 'Message 1',
    'role' => 'user' // <--- الإضافة المطلوبة
  ]);

  $messages = $conversation->messages;

  expect($messages)->toHaveCount(2)
    ->and($messages->first()->sequence)->toBe(1)
    ->and($messages->last()->sequence)->toBe(2);
});

test('it can retrieve the last message', function () {
  $conversation = AiConversation::create([
    'user_id' => 1,
    'title' => 'Test',
    'provisioned_project_id' => 1,
    'status' => 'active',
  ]);

  // تأكد من إضافة 'role' هنا أيضاً!
  AiMessage::create([
    'conversation_id' => $conversation->id,
    'sequence' => 1,
    'content' => 'First',
    'role' => 'user'
  ]);

  AiMessage::create([
    'conversation_id' => $conversation->id,
    'sequence' => 2,
    'content' => 'Last',
    'role' => 'assistant' // القيمة التي تناسب تطبيقك
  ]);

  $lastMessage = $conversation->lastMessage()->first();

  expect($lastMessage->content)->toBe('Last')
    ->and($lastMessage->sequence)->toBe(2);
});

test('it casts provisioned_project_id to integer', function () {
  $conversation = AiConversation::create([
    'user_id' => 1,
    'title' => 'Test',
    'provisioned_project_id' => '55', // تمرير نص
    'status' => 'active',
  ]);

  // التأكد من أن الكاستنج (Casting) يعمل ويحول القيمة إلى رقم صحيح
  expect($conversation->provisioned_project_id)->toBeInt()
    ->and($conversation->provisioned_project_id)->toBe(55);
});
