<?php

use App\Domains\AI\DTOs\SendMessageDTO;
use Illuminate\Http\Request;

test('it creates DTO correctly with all provided values', function () {
  $request = new Request([
    'content' => 'Hello AI',
    'conversation_id' => 123,
    'action' => SendMessageDTO::ACTION_PROVISION,
  ]);

  $dto = SendMessageDTO::fromRequest($request, 1);

  expect($dto->userId)->toBe(1)
    ->and($dto->content)->toBe('Hello AI')
    ->and($dto->conversationId)->toBe(123)
    ->and($dto->action)->toBe(SendMessageDTO::ACTION_PROVISION);
});

test('it uses default values when optional fields are missing', function () {
  // إرسال طلب يحتوي فقط على المحتوى المطلوب
  $request = new Request([
    'content' => 'Just chat',
  ]);

  $dto = SendMessageDTO::fromRequest($request, 5);

  expect($dto->conversationId)->toBeNull()
    ->and($dto->action)->toBe(SendMessageDTO::ACTION_CHAT); // القيمة الافتراضية
});
