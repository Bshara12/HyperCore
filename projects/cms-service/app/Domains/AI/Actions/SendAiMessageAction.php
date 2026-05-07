<?php

namespace App\Domains\AI\Actions;

use App\Domains\AI\DTOs\ProvisionProjectFromSchemaDTO;
use App\Domains\AI\DTOs\SendMessageDTO;
use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;
use Illuminate\Support\Str;

class SendAiMessageAction
{
  public function __construct(
    private AiConversationRepositoryInterface $repository,
    private GenerateProjectSchemaAction       $generateSchema,
    private ProvisionProjectFromSchemaAction  $provisionProject,
  ) {}

  public function execute(SendMessageDTO $dto): array
  {
    // ─── Step 1: جيب أو أنشئ محادثة ──────────────────────
    $conversation = $this->resolveConversation($dto);

    // ─── Step 2: احفظ رسالة المستخدم ──────────────────────
    $lastSequence = $this->repository->getLastSequence($conversation->id);

    $this->repository->addMessage(
      conversationId: $conversation->id,
      role: 'user',
      content: $dto->content,
      schema: null,
      sequence: $lastSequence + 1,
    );

    // ─── Step 3: اختر العملية بناءً على الـ action ─────────
    return match ($dto->action) {

      SendMessageDTO::ACTION_PROVISION => $this->handleProvision(
        $conversation,
        $dto,
        $lastSequence
      ),

      default => $this->handleChat(
        $conversation,
        $dto,
        $lastSequence
      ),
    };
  }

  // =========================================================
  // Chat — توليد/تعديل الـ Schema
  // =========================================================
  private function handleChat(
    $conversation,
    SendMessageDTO $dto,
    int $lastSequence
  ): array {
    // ─── جيب تاريخ المحادثة لإرساله للـ AI ─────────────
    $history = $this->buildHistoryForAI($conversation->id);

    // ─── استدعاء الـ AI مع الـ history ──────────────────
    $aiResponse = $this->generateSchema->execute(
      $dto->content,
      $history
    );

    // ─── احفظ رد الـ AI ──────────────────────────────────
    $aiMessage = $this->repository->addMessage(
      conversationId: $conversation->id,
      role: 'assistant',
      content: $aiResponse['ai_chat_bubble'],
      schema: $aiResponse['technical_schema'] ?? null,
      sequence: $lastSequence + 2,
    );

    // ─── حدّث العنوان لو محادثة جديدة ───────────────────
    if (!$dto->conversationId) {
      $title = $this->generateTitle($dto->content);
      $this->repository->updateConversationTitle($conversation->id, $title);
      $conversation->title = $title;
    }

    return [
      'type'               => 'schema_generated',
      'conversation_id'    => $conversation->id,
      'conversation_title' => $conversation->title,
      'message_id'         => $aiMessage->id,
      'ai_chat_bubble'     => $aiResponse['ai_chat_bubble'],
      'technical_schema'   => $aiResponse['technical_schema'] ?? null,
      'is_new_conversation' => !$dto->conversationId,
      'can_provision'      => !empty($aiResponse['technical_schema']),
    ];
  }

  // =========================================================
  // Provision — إنشاء المشروع
  // =========================================================
  private function handleProvision(
    $conversation,
    SendMessageDTO $dto,
    int $lastSequence
  ): array {
    // ─── تحقق إن المحادثة ما فيها مشروع منشأ مسبقاً ─────
    if ($conversation->provisioned_project_id) {
      $aiMessage = $this->repository->addMessage(
        conversationId: $conversation->id,
        role: 'assistant',
        content: 'تم إنشاء المشروع مسبقاً في هذه المحادثة. لا يمكن إنشاء مشروع جديد من نفس المحادثة.',
        schema: null,
        sequence: $lastSequence + 2,
      );

      return [
        'type'                   => 'already_provisioned',
        'conversation_id'        => $conversation->id,
        'provisioned_project_id' => $conversation->provisioned_project_id,
        'message_id'             => $aiMessage->id,
        'ai_chat_bubble'         => 'تم إنشاء المشروع مسبقاً في هذه المحادثة.',
      ];
    }

    // ─── جيب آخر schema من رسائل الـ assistant ───────────
    $lastSchema = $this->getLastSchemaFromConversation($conversation->id);

    if (!$lastSchema) {
      $aiMessage = $this->repository->addMessage(
        conversationId: $conversation->id,
        role: 'assistant',
        content: 'لم يتم توليد مخطط تقني بعد. يرجى وصف مشروعك أولاً.',
        schema: null,
        sequence: $lastSequence + 2,
      );

      return [
        'type'             => 'no_schema',
        'conversation_id'  => $conversation->id,
        'message_id'       => $aiMessage->id,
        'ai_chat_bubble'   => 'لم يتم توليد مخطط تقني بعد. يرجى وصف مشروعك أولاً.',
      ];
    }

    // ─── إنشاء المشروع ────────────────────────────────────
    try {
      $provisionDto = ProvisionProjectFromSchemaDTO::fromRequest(
        $lastSchema,
        $dto->userId
      );

      $result = $this->provisionProject->execute($provisionDto);

      // ─── احفظ رسالة نجاح الإنشاء ─────────────────────
      $successContent = "✅ تم إنشاء المشروع \"{$result['project']['name']}\" بنجاح!\n\n"
        . "📊 تفاصيل المشروع:\n"
        . "• المعرّف: {$result['project']['id']}\n"
        . "• عدد أنواع البيانات: {$result['total_types']}\n"
        . "• عدد الحقول الكلي: {$result['total_fields']}\n"
        . "• الموديولات المفعّلة: " . implode(', ', $result['modules']);

      $aiMessage = $this->repository->addMessage(
        conversationId: $conversation->id,
        role: 'assistant',
        content: $successContent,
        schema: null,
        sequence: $lastSequence + 2,
      );

      // ─── وسّم المحادثة بالمشروع المنشأ ──────────────
      $this->repository->markAsProvisioned(
        $conversation->id,
        $result['project']['id']
      );

      // ─── وسّم الرسالة الأخيرة للـ AI كـ provisioned ──
      $this->markLastAssistantMessageProvisioned($conversation->id, $aiMessage->id);

      return [
        'type'                   => 'project_provisioned',
        'conversation_id'        => $conversation->id,
        'message_id'             => $aiMessage->id,
        'ai_chat_bubble'         => $successContent,
        'provisioned_project'    => $result['project'],
        'total_types'            => $result['total_types'],
        'total_fields'           => $result['total_fields'],
        'data_types'             => $result['data_types'],
      ];
    } catch (\Throwable $e) {
      $errorContent = "❌ فشل إنشاء المشروع: " . $e->getMessage();

      $aiMessage = $this->repository->addMessage(
        conversationId: $conversation->id,
        role: 'assistant',
        content: $errorContent,
        schema: null,
        sequence: $lastSequence + 2,
      );

      return [
        'type'             => 'provision_failed',
        'conversation_id'  => $conversation->id,
        'message_id'       => $aiMessage->id,
        'ai_chat_bubble'   => $errorContent,
        'error'            => $e->getMessage(),
      ];
    }
  }

  // =========================================================
  // Helpers
  // =========================================================

  private function resolveConversation(SendMessageDTO $dto)
  {
    if ($dto->conversationId) {
      $conversation = $this->repository->findConversationForUser(
        $dto->conversationId,
        $dto->userId
      );

      if (!$conversation) {
        throw new \Exception('Conversation not found or access denied.', 404);
      }

      return $conversation;
    }

    // محادثة جديدة
    return $this->repository->createConversation(
      $dto->userId,
      $this->generateTitle($dto->content)
    );
  }

  // بناء الـ history للـ AI — فقط الرسائل السابقة (بدون الرسالة الحالية)
  private function buildHistoryForAI(int $conversationId): array
  {
    $messages = $this->repository->getMessages($conversationId);

    // نستثني آخر رسالة لأنها الرسالة الحالية للمستخدم
    // التي أضفناها في Step 2
    $history = $messages->slice(0, -1);

    if ($history->isEmpty()) {
      return [];
    }

    return $history->map(fn($m) => [
      'role'    => $m->role,
      'content' => $m->content,
      'schema'  => $m->schema,
    ])->values()->toArray();
  }

  // جيب آخر schema من رسائل الـ assistant
  private function getLastSchemaFromConversation(int $conversationId): ?array
  {
    $messages = $this->repository->getMessages($conversationId);

    $lastAssistantWithSchema = $messages
      ->where('role', 'assistant')
      ->filter(fn($m) => !empty($m->schema))
      ->last();

    return $lastAssistantWithSchema?->schema;
  }

  // تحديد الرسالة السابقة للـ assistant كـ provisioned
  private function markLastAssistantMessageProvisioned(
    int $conversationId,
    int $currentMessageId
  ): void {
    $messages = $this->repository->getMessages($conversationId);

    $lastAssistantWithSchema = $messages
      ->where('role', 'assistant')
      ->filter(fn($m) => !empty($m->schema))
      ->last();

    if ($lastAssistantWithSchema) {
      $this->repository->markMessageAsProvisioned($lastAssistantWithSchema->id);
    }
  }

  private function generateTitle(string $text): string
  {
    $title = Str::limit(strip_tags($text), 60);
    $title = preg_replace('/[{}\[\]":]/', '', $title);
    return trim($title) ?: 'New Conversation';
  }
}
