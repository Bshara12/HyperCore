<?php

use App\Domains\AI\Actions\SendAiMessageAction;
use App\Domains\AI\Actions\GenerateProjectSchemaAction;
use App\Domains\AI\Actions\ProvisionProjectFromSchemaAction;
use App\Domains\AI\DTOs\SendMessageDTO;
use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;
use App\Domains\CMS\Services\RabbitMQPublisher;
use App\Models\AiConversation;
use App\Models\AiMessage; // استدعاء موديل الرسائل لحل الـ TypeError والـ getAttribute
use Illuminate\Support\Collection;

// تأمين الخدمات الخارجية كقاعدة عامة
beforeEach(function () {
    test()->spy(RabbitMQPublisher::class);
});

/**
 * دالة مساعدة لإنشاء كائن حقيقي من موديل المحادثة
 */
function createFakeConversation(array $attributes): AiConversation
{
    $conversation = new AiConversation();
    foreach ($attributes as $key => $value) {
        $conversation->{$key} = $value;
    }
    return $conversation;
}

/**
 * دالة مساعدة لإنشاء كائن حقيقي من موديل الرسالة
 * لتخطي قيود الـ Return Type الصارمة ودوال الـ Eloquent مثل getAttribute()
 */
function createFakeMessage(array $attributes): AiMessage
{
    $message = new AiMessage();
    foreach ($attributes as $key => $value) {
        $message->{$key} = $value;
    }
    return $message;
}

// ─── 1. اختبارات مسار الـ Chat (توليد الـ Schema) ───────────────────────────────────

test('it handles chat for a new conversation, generates title and returns schema details', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $generateSchemaMock = mock(GenerateProjectSchemaAction::class);
    $provisionProjectMock = mock(ProvisionProjectFromSchemaAction::class);

    $dto = new SendMessageDTO(
        userId: 1,
        content: 'Create a blog system with {tags}',
        conversationId: null,
        action: SendMessageDTO::ACTION_CHAT
    );

    $fakeConversation = createFakeConversation(['id' => 10, 'title' => 'New Conversation']);
    $fakeUserMessage = createFakeMessage(['id' => 199]);
    $fakeAiMessage = createFakeMessage(['id' => 200]);

    $repoMock->shouldReceive('createConversation')
        ->once()
        ->with(1, 'Create a blog system with tags')
        ->andReturn($fakeConversation);

    $repoMock->shouldReceive('getLastSequence')->once()->with(10)->andReturn(0);

    // تزويد إرجاع متوافق لدالة إضافة رسالة المستخدم
    $repoMock->shouldReceive('addMessage')
        ->once()
        ->with(10, 'user', $dto->content, null, 1)
        ->andReturn($fakeUserMessage);

    $repoMock->shouldReceive('getMessages')->once()->with(10)->andReturn(new Collection([
        createFakeMessage(['role' => 'user', 'content' => $dto->content, 'schema' => null])
    ]));

    $aiResponse = [
        'ai_chat_bubble' => 'Here is your schema',
        'technical_schema' => ['project_info' => ['name' => 'Blog']]
    ];
    $generateSchemaMock->shouldReceive('execute')
        ->once()
        ->with($dto->content, [])
        ->andReturn($aiResponse);

    // تزويد إرجاع متوافق لدالة إضافة رسالة الـ Assistant
    $repoMock->shouldReceive('addMessage')
        ->once()
        ->with(10, 'assistant', 'Here is your schema', $aiResponse['technical_schema'], 2)
        ->andReturn($fakeAiMessage);

    $repoMock->shouldReceive('updateConversationTitle')->once()->with(10, 'Create a blog system with tags');

    $action = new SendAiMessageAction($repoMock, $generateSchemaMock, $provisionProjectMock);
    $result = $action->execute($dto);

    expect($result)->toEqual([
        'type' => 'schema_generated',
        'conversation_id' => 10,
        'conversation_title' => 'Create a blog system with tags',
        'message_id' => 200,
        'ai_chat_bubble' => 'Here is your schema',
        'technical_schema' => $aiResponse['technical_schema'],
        'is_new_conversation' => true,
        'can_provision' => true,
    ]);
});

test('it handles chat for an existing conversation and builds history correctly', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $generateSchemaMock = mock(GenerateProjectSchemaAction::class);
    $provisionProjectMock = mock(ProvisionProjectFromSchemaAction::class);

    $dto = new SendMessageDTO(
        userId: 1,
        content: 'Add comments to it',
        conversationId: 10,
        action: SendMessageDTO::ACTION_CHAT
    );

    $fakeConversation = createFakeConversation(['id' => 10, 'title' => 'Existing Blog Title']);
    $fakeUserMessage = createFakeMessage(['id' => 199]);
    $fakeAiMessage = createFakeMessage(['id' => 201]);

    $repoMock->shouldReceive('findConversationForUser')->once()->with(10, 1)->andReturn($fakeConversation);
    $repoMock->shouldReceive('getLastSequence')->once()->with(10)->andReturn(2);
    
    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'user', $dto->content, null, 3)
        ->andReturn($fakeUserMessage);

    $historyMessages = new Collection([
        createFakeMessage(['role' => 'user', 'content' => 'First msg', 'schema' => null]),
        createFakeMessage(['role' => 'assistant', 'content' => 'First response', 'schema' => ['old_schema']]),
        createFakeMessage(['role' => 'user', 'content' => 'Add comments to it', 'schema' => null]),
    ]);
    $repoMock->shouldReceive('getMessages')->once()->with(10)->andReturn($historyMessages);

    $expectedHistoryForAi = [
        ['role' => 'user', 'content' => 'First msg', 'schema' => null],
        ['role' => 'assistant', 'content' => 'First response', 'schema' => ['old_schema']]
    ];

    $aiResponse = [
        'ai_chat_bubble' => 'Comments added',
        'technical_schema' => null
    ];
    $generateSchemaMock->shouldReceive('execute')->once()->with($dto->content, $expectedHistoryForAi)->andReturn($aiResponse);
    
    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'assistant', 'Comments added', null, 4)
        ->andReturn($fakeAiMessage);

    $action = new SendAiMessageAction($repoMock, $generateSchemaMock, $provisionProjectMock);
    $result = $action->execute($dto);

    expect($result['is_new_conversation'])->toBeFalse()
        ->and($result['can_provision'])->toBeFalse();
});

test('it throws an exception if an existing conversation is not found or access is denied', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $repoMock->shouldReceive('findConversationForUser')->once()->with(999, 1)->andReturn(null);

    $dto = new SendMessageDTO(userId: 1, content: 'Hello', conversationId: 999, action: SendMessageDTO::ACTION_CHAT);
    $action = new SendAiMessageAction($repoMock, mock(GenerateProjectSchemaAction::class), mock(ProvisionProjectFromSchemaAction::class));

    expect(fn() => $action->execute($dto))
        ->toThrow(\Exception::class, 'Conversation not found or access denied.', 404);
});


// ─── 2. اختبارات مسار الـ Provision (إنشاء المشروع) ───────────────────────────────

test('it returns early if the conversation has already provisioned a project', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $dto = new SendMessageDTO(userId: 1, content: 'Go', conversationId: 10, action: SendMessageDTO::ACTION_PROVISION);

    $fakeConversation = createFakeConversation(['id' => 10, 'provisioned_project_id' => 55]);
    $fakeUserMessage = createFakeMessage(['id' => 199]);
    $fakeAiMessage = createFakeMessage(['id' => 300]);

    $repoMock->shouldReceive('findConversationForUser')->once()->with(10, 1)->andReturn($fakeConversation);
    $repoMock->shouldReceive('getLastSequence')->once()->with(10)->andReturn(4);
    
    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'user', 'Go', null, 5)
        ->andReturn($fakeUserMessage);

    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'assistant', 'تم إنشاء المشروع مسبقاً في هذه المحادثة. لا يمكن إنشاء مشروع جديد من نفس المحادثة.', null, 6)
        ->andReturn($fakeAiMessage);

    $action = new SendAiMessageAction($repoMock, mock(GenerateProjectSchemaAction::class), mock(ProvisionProjectFromSchemaAction::class));
    $result = $action->execute($dto);

    expect($result)->toEqual([
        'type' => 'already_provisioned',
        'conversation_id' => 10,
        'provisioned_project_id' => 55,
        'message_id' => 300,
        'ai_chat_bubble' => 'تم إنشاء المشروع مسبقاً في هذه المحادثة.',
    ]);
});

test('it returns early if no technical schema is found in the conversation assistant messages', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $dto = new SendMessageDTO(userId: 1, content: 'Go', conversationId: 10, action: SendMessageDTO::ACTION_PROVISION);

    $fakeConversation = createFakeConversation(['id' => 10, 'provisioned_project_id' => null]);
    $fakeUserMessage = createFakeMessage(['id' => 199]);
    $fakeAiMessage = createFakeMessage(['id' => 301]);

    $repoMock->shouldReceive('findConversationForUser')->once()->with(10, 1)->andReturn($fakeConversation);
    $repoMock->shouldReceive('getLastSequence')->once()->with(10)->andReturn(2);
    
    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'user', 'Go', null, 3)
        ->andReturn($fakeUserMessage);

    $messages = new Collection([
        createFakeMessage(['role' => 'user', 'content' => 'hi', 'schema' => null]),
        createFakeMessage(['role' => 'assistant', 'content' => 'hello standard chat', 'schema' => null]),
    ]);
    $repoMock->shouldReceive('getMessages')->once()->with(10)->andReturn($messages);

    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'assistant', 'لم يتم توليد مخطط تقني بعد. يرجى وصف مشروعك أولاً.', null, 4)
        ->andReturn($fakeAiMessage);

    $action = new SendAiMessageAction($repoMock, mock(GenerateProjectSchemaAction::class), mock(ProvisionProjectFromSchemaAction::class));
    $result = $action->execute($dto);

    expect($result)->toEqual([
        'type' => 'no_schema',
        'conversation_id' => 10,
        'message_id' => 301,
        'ai_chat_bubble' => 'لم يتم توليد مخطط تقني بعد. يرجى وصف مشروعك أولاً.',
    ]);
});

test('it provisions the project successfully when a valid schema exists', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $provisionProjectMock = mock(ProvisionProjectFromSchemaAction::class);
    $dto = new SendMessageDTO(userId: 1, content: 'Deploy please', conversationId: 10, action: SendMessageDTO::ACTION_PROVISION);

    $fakeConversation = createFakeConversation(['id' => 10, 'provisioned_project_id' => null]);
    $fakeUserMessage = createFakeMessage(['id' => 199]);
    $fakeAiMessage = createFakeMessage(['id' => 302]);

    $repoMock->shouldReceive('findConversationForUser')->once()->with(10, 1)->andReturn($fakeConversation);
    $repoMock->shouldReceive('getLastSequence')->once()->with(10)->andReturn(2);
    
    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'user', 'Deploy please', null, 3)
        ->andReturn($fakeUserMessage);

    $validSchema = [
        'project_info' => ['name' => 'Store Shop', 'languages' => ['ar'], 'modules' => ['cms']],
        'custom_data_types' => [],
        'relations' => []
    ];
    $messages = new Collection([
        createFakeMessage(['id' => 99, 'role' => 'assistant', 'content' => 'Here is schema', 'schema' => $validSchema]),
    ]);
    $repoMock->shouldReceive('getMessages')->twice()->with(10)->andReturn($messages);

    $provisionResult = [
        'project' => ['id' => 77, 'name' => 'Store Shop'],
        'total_types' => 3,
        'total_fields' => 12,
        'modules' => ['cms', 'ecommerce'],
        'data_types' => []
    ];
    $provisionProjectMock->shouldReceive('execute')->once()->andReturn($provisionResult);

    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'assistant', Mockery::type('string'), null, 4)
        ->andReturn($fakeAiMessage);

    $repoMock->shouldReceive('markAsProvisioned')->once()->with(10, 77);
    $repoMock->shouldReceive('markMessageAsProvisioned')->once()->with(99);

    $action = new SendAiMessageAction($repoMock, mock(GenerateProjectSchemaAction::class), $provisionProjectMock);
    $result = $action->execute($dto);

    expect($result['type'])->toBe('project_provisioned')
        ->and($result['provisioned_project']['id'])->toBe(77)
        ->and($result['total_types'])->toBe(3);
});

test('it handles failures during provision process and saves failure message', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $provisionProjectMock = mock(ProvisionProjectFromSchemaAction::class);
    $dto = new SendMessageDTO(userId: 1, content: 'Deploy now', conversationId: 10, action: SendMessageDTO::ACTION_PROVISION);

    $fakeConversation = createFakeConversation(['id' => 10, 'provisioned_project_id' => null]);
    $fakeUserMessage = createFakeMessage(['id' => 199]);
    $fakeAiMessage = createFakeMessage(['id' => 500]);

    $repoMock->shouldReceive('findConversationForUser')->once()->with(10, 1)->andReturn($fakeConversation);
    $repoMock->shouldReceive('getLastSequence')->once()->with(10)->andReturn(1);
    
    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'user', 'Deploy now', null, 2)
        ->andReturn($fakeUserMessage);

    $validSchema = ['project_info' => ['name' => 'Error Prone']];
    $messages = new Collection([
        createFakeMessage(['id' => 99, 'role' => 'assistant', 'content' => 'Here schema', 'schema' => $validSchema]),
    ]);
    $repoMock->shouldReceive('getMessages')->once()->with(10)->andReturn($messages);

    $provisionProjectMock->shouldReceive('execute')->once()->andThrow(new \Exception('Database Connection Dropped'));

    $repoMock->shouldReceive('addMessage')->once()
        ->with(10, 'assistant', '❌ فشل إنشاء المشروع: Database Connection Dropped', null, 3)
        ->andReturn($fakeAiMessage);

    $action = new SendAiMessageAction($repoMock, mock(GenerateProjectSchemaAction::class), $provisionProjectMock);
    $result = $action->execute($dto);

    expect($result)->toEqual([
        'type' => 'provision_failed',
        'conversation_id' => 10,
        'message_id' => 500,
        'ai_chat_bubble' => '❌ فشل إنشاء المشروع: Database Connection Dropped',
        'error' => 'Database Connection Dropped',
    ]);
});


// ─── 3. اختبار التوابع المساعدة الصامتة (Edge Cases Helpers) ───────────────────────────

test('it falls back to New Conversation string if generated title is empty', function () {
    $repoMock = mock(AiConversationRepositoryInterface::class);
    $dto = new SendMessageDTO(
        userId: 1,
        content: '   [[[[[[ ]]]]]] :::: """""   ',
        conversationId: null,
        action: SendMessageDTO::ACTION_CHAT
    );

    $fakeConversation = createFakeConversation(['id' => 1]);
    $fakeUserMessage = createFakeMessage(['id' => 199]);

    $repoMock->shouldReceive('createConversation')->once()->with(1, 'New Conversation')->andReturn($fakeConversation);
    $repoMock->shouldReceive('getLastSequence')->andReturn(0);
    
    $repoMock->shouldReceive('addMessage')->andReturn($fakeUserMessage);
    $repoMock->shouldReceive('getMessages')->andReturn(new Collection());
    $repoMock->shouldReceive('updateConversationTitle');

    $generateSchemaMock = mock(GenerateProjectSchemaAction::class);
    $generateSchemaMock->shouldReceive('execute')->andReturn(['ai_chat_bubble' => 'done']);

    $action = new SendAiMessageAction($repoMock, $generateSchemaMock, mock(ProvisionProjectFromSchemaAction::class));
    $action->execute($dto);
});