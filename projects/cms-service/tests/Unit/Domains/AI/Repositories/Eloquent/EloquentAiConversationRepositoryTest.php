<?php

namespace Tests\Unit\Domains\AI\Repositories;

use App\Domains\AI\Repositories\Eloquent\EloquentAiConversationRepository;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentAiConversationRepository();
  $this->user = User::factory()->create();
});

// ─── Conversations Tests ────────────────────────────────────────

test('createConversation inserts a new active conversation', function () {
  $conversation = $this->repository->createConversation($this->user->id, 'New Chat');

  $this->assertDatabaseHas('ai_conversations', [
    'user_id' => $this->user->id,
    'title' => 'New Chat',
    'status' => 'active',
  ]);
  expect($conversation->id)->toBeGreaterThan(0);
});

test('findConversation returns conversation by id', function () {
  $conv = AiConversation::factory()->create();
  $found = $this->repository->findConversation($conv->id);

  expect($found->id)->toBe($conv->id);
});

test('findConversationForUser returns conversation only if it belongs to user', function () {
  $conv = AiConversation::factory()->create(['user_id' => $this->user->id]);
  $otherUserConv = AiConversation::factory()->create(); // belongs to someone else

  expect($this->repository->findConversationForUser($conv->id, $this->user->id))->not->toBeNull();
  expect($this->repository->findConversationForUser($otherUserConv->id, $this->user->id))->toBeNull();
});

test('listConversations returns paginated active conversations', function () {
  AiConversation::factory()->count(5)->create([
    'user_id' => $this->user->id,
    'status' => 'active'
  ]);
  // Create inactive one
  AiConversation::factory()->create(['user_id' => $this->user->id, 'status' => 'archived']);

  $conversations = $this->repository->listConversations($this->user->id, 2);

  expect($conversations)->toBeInstanceOf(LengthAwarePaginator::class)
    ->and($conversations->total())->toBe(5); // Only active ones
});

test('updateConversationTitle changes title in database', function () {
  $conv = AiConversation::factory()->create(['user_id' => $this->user->id]);

  $this->repository->updateConversationTitle($conv->id, 'Updated Title');

  $this->assertDatabaseHas('ai_conversations', ['id' => $conv->id, 'title' => 'Updated Title']);
});

test('markAsProvisioned sets the project id', function () {
  $conv = AiConversation::factory()->create();
  $projectId = 99;

  $this->repository->markAsProvisioned($conv->id, $projectId);

  $this->assertDatabaseHas('ai_conversations', ['id' => $conv->id, 'provisioned_project_id' => $projectId]);
});

test('deleteConversation removes the record', function () {
  $conv = AiConversation::factory()->create();

  $this->repository->deleteConversation($conv->id);

  // استبدل assertDatabaseMissing بـ assertSoftDeleted
  $this->assertSoftDeleted('ai_conversations', ['id' => $conv->id]);
});

// ─── Messages Tests ─────────────────────────────────────────────

test('addMessage creates a message with correct sequence', function () {
  $conv = AiConversation::factory()->create();

  $message = $this->repository->addMessage($conv->id, 'user', 'Hello', ['key' => 'val'], 1);

  $this->assertDatabaseHas('ai_messages', [
    'conversation_id' => $conv->id,
    'role' => 'user',
    'content' => 'Hello',
    'sequence' => 1,
    'is_provisioned' => false
  ]);
});

test('getMessages returns messages ordered by sequence', function () {
  $conv = AiConversation::factory()->create();
  AiMessage::factory()->create(['conversation_id' => $conv->id, 'sequence' => 2, 'content' => 'Second']);
  AiMessage::factory()->create(['conversation_id' => $conv->id, 'sequence' => 1, 'content' => 'First']);

  $messages = $this->repository->getMessages($conv->id);

  expect($messages->first()->content)->toBe('First')
    ->and($messages->last()->content)->toBe('Second');
});

test('getLastSequence returns max sequence or 0 if empty', function () {
  $conv = AiConversation::factory()->create();

  // Case 1: Empty
  expect($this->repository->getLastSequence($conv->id))->toBe(0);

  // Case 2: Has sequence
  AiMessage::factory()->create(['conversation_id' => $conv->id, 'sequence' => 5]);
  expect($this->repository->getLastSequence($conv->id))->toBe(5);
});

test('markMessageAsProvisioned updates is_provisioned to true', function () {
  $msg = AiMessage::factory()->create(['is_provisioned' => false]);

  $this->repository->markMessageAsProvisioned($msg->id);

  $this->assertDatabaseHas('ai_messages', ['id' => $msg->id, 'is_provisioned' => true]);
});

test('updateConversationStatus changes status in database', function () {
  // 1. Arrange: إنشاء محادثة بحالة 'active'
  $conv = AiConversation::factory()->create(['status' => 'active']);

  // 2. Act: تحديث الحالة إلى 'archived'
  $this->repository->updateConversationStatus($conv->id, 'archived');

  // 3. Assert: التحقق من أن القاعدة تحتوي على السجل بالحالة الجديدة
  $this->assertDatabaseHas('ai_conversations', [
    'id' => $conv->id,
    'status' => 'archived'
  ]);
});
