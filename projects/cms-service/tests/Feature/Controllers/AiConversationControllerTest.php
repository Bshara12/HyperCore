<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\AI\Services\AiConversationService;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class AiConversationControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $serviceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->serviceMock = $this->mock(AiConversationService::class);
    $this->withHeaders(['Accept' => 'application/json']);
  }

  /**
   * مساعدة لإضافة المستخدم للـ Request Attributes بشكل ديناميكي ومستمر
   */
  private function actingAsUser(int $userId = 1): void
  {
    $this->app->rebinding('request', function ($app, $request) use ($userId) {
      $request->attributes->set('auth_user', (object)['id' => $userId]);
    });
  }

  #[Test]
  public function it_lists_conversations()
  {
    $this->actingAsUser(1);
    $mockData = [['id' => 1, 'title' => 'Test Conversation']];

    $this->serviceMock->shouldReceive('list')
      ->once()
      ->with(1, 15)
      ->andReturn($mockData);

    $response = $this->getJson(route('ai-conversations.index', ['per_page' => 15]));

    $response->assertStatus(200)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }

  #[Test]
  public function it_stores_a_message_successfully()
  {
    $this->actingAsUser(1);
    $mockData = ['id' => 2, 'response' => 'Hello!'];

    $this->serviceMock->shouldReceive('send')
      ->once()
      ->andReturn($mockData);

    $response = $this->postJson(route('ai-conversations.store'), [
      'content' => 'Hello AI',
      'conversation_id' => null,
      'action' => 'chat'
    ]);

    $response->assertStatus(201)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }

  #[Test]
  public function it_fails_validation_when_storing_empty_message()
  {
    $this->actingAsUser(1);

    $response = $this->postJson(route('ai-conversations.store'), [
      'content' => '',
    ]);

    $response->assertStatus(422)
      ->assertJsonStructure(['message', 'errors']);
  }

  /**
   * اختبار الـ Catch في دالة store والـ errorResponse
   */
  #[Test]
  public function it_handles_exception_when_storing_a_message()
  {
    $this->actingAsUser(1);

    // إجبار الـ Service على رمي استثناء
    $this->serviceMock->shouldReceive('send')
      ->once()
      ->andThrow(new \Exception('Failed to send message to AI', 500));

    $response = $this->postJson(route('ai-conversations.store'), [
      'content' => 'Hello AI',
      'conversation_id' => null,
      'action' => 'chat'
    ]);

    $response->assertStatus(500)
      ->assertJson([
        'success' => false,
        'message' => 'Failed to send message to AI'
      ]);
  }

  #[Test]
  public function it_shows_a_conversation()
  {
    $this->actingAsUser(1);
    $mockData = ['id' => 1, 'messages' => []];

    $this->serviceMock->shouldReceive('get')
      ->once()
      ->with(1, 1)
      ->andReturn($mockData);

    $response = $this->getJson(route('ai-conversations.show', ['id' => 1]));

    $response->assertStatus(200)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }

  /**
   * اختبار الـ Catch في دالة show والـ errorResponse
   */
  #[Test]
  public function it_handles_exception_when_showing_a_conversation()
  {
    $this->actingAsUser(1);

    // إجبار الـ Service على رمي استثناء بكود مخصص (مثلاً 404)
    $this->serviceMock->shouldReceive('get')
      ->once()
      ->with(1, 1)
      ->andThrow(new \Exception('Conversation not found', 404));

    $response = $this->getJson(route('ai-conversations.show', ['id' => 1]));

    $response->assertStatus(404)
      ->assertJson([
        'success' => false,
        'message' => 'Conversation not found'
      ]);
  }

  #[Test]
  public function it_deletes_a_conversation()
  {
    $this->actingAsUser(1);

    $this->serviceMock->shouldReceive('delete')
      ->once()
      ->with(1, 1);

    $response = $this->deleteJson(route('ai-conversations.destroy', ['id' => 1]));

    $response->assertStatus(200)
      ->assertJson(['success' => true]);
  }

  /**
   * اختبار الـ Catch في دالة destroy والـ errorResponse
   */
  #[Test]
  public function it_handles_exception_when_deleting_a_conversation()
  {
    $this->actingAsUser(1);

    $this->serviceMock->shouldReceive('delete')
      ->once()
      ->with(1, 1)
      ->andThrow(new \Exception('Unauthorized or database error', 403));

    $response = $this->deleteJson(route('ai-conversations.destroy', ['id' => 1]));

    $response->assertStatus(403)
      ->assertJson([
        'success' => false,
        'message' => 'Unauthorized or database error'
      ]);
  }
}
