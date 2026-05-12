<?php

namespace App\Http\Controllers;

use App\Domains\AI\DTOs\SendMessageDTO;
use App\Domains\AI\Services\AiConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConversationController extends Controller
{
  public function __construct(
    private AiConversationService $service
  ) {}

  public function index(Request $request): JsonResponse
  {
    $userId  = $this->getAuthUserId($request);
    $perPage = (int) $request->get('per_page', 15);
    $result  = $this->service->list($userId, $perPage);

    return $this->successResponse($result);
  }

  public function store(Request $request): JsonResponse
  {
    $request->validate([
      'content'         => 'required|string|min:3|max:3000',
      'conversation_id' => 'nullable|integer|exists:ai_conversations,id',
      'action'          => 'nullable|string|in:chat,provision',
    ]);

    $userId = $this->getAuthUserId($request);

    try {
      $dto    = SendMessageDTO::fromRequest($request, $userId);
      $result = $this->service->send($dto);

      return $this->successResponse($result, 201);
    } catch (\Exception $e) {
      return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
    }
  }

  public function show(Request $request, int $id): JsonResponse
  {
    $userId = $this->getAuthUserId($request);

    try {
      $result = $this->service->get($id, $userId);
      return $this->successResponse($result);
    } catch (\Exception $e) {
      return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
    }
  }

  public function destroy(Request $request, int $id): JsonResponse
  {
    $userId = $this->getAuthUserId($request);

    try {
      $this->service->delete($id, $userId);
      return $this->successResponse(['message' => 'Conversation deleted successfully.']);
    } catch (\Exception $e) {
      return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
    }
  }

  // ─────────────────────────────────────────────────────────
  private function getAuthUserId(Request $request): int
  {
    $user = $request->attributes->get('auth_user');
    return $user->id ?? $user['id'];
  }

  private function successResponse(array $data, int $status = 200): JsonResponse
  {
    return response()->json(
      ['success' => true, 'data' => $data],
      $status,
      ['Content-Type' => 'application/json; charset=UTF-8'],
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
  }

  private function errorResponse(string $message, int $status = 500): JsonResponse
  {
    $safeStatus = ($status >= 100 && $status <= 599) ? $status : 500;
    return response()->json(
      ['success' => false, 'message' => $message],
      $safeStatus,
      ['Content-Type' => 'application/json; charset=UTF-8'],
      JSON_UNESCAPED_UNICODE
    );
  }
}
