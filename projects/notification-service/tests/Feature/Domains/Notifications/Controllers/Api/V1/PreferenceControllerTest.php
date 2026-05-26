<?php

namespace Tests\Feature\Domains\Notifications\Controllers\Api\V1;

use Tests\TestCase;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\PreferenceController;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationPreferencesRequest;
use App\Models\Domains\Notifications\Models\NotificationPreference; // تأكد من الـ Namespace الفعلي للموديل لديك
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;

class PreferenceControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $preferenceServiceMock;
  private array $mockUserActor;
  private array $mockProjectAttribute;

  protected function setUp(): void
  {
    parent::setUp();

    $this->preferenceServiceMock = $this->mock(NotificationPreferenceService::class);

    $this->mockProjectAttribute = ['string' => 'project-omega'];
    $this->mockUserActor = [
      'id' => 'user-abc-123',
      'permessions' => ['manage_preferences']
    ];
  }

  private function createMockPreference(): NotificationPreference
  {
    $preferenceClass = class_exists(NotificationPreference::class)
      ? NotificationPreference::class
      : \Illuminate\Database\Eloquent\Model::class;

    return (new $preferenceClass())->forceFill([
      'id' => 'pref-123',
      'user_id' => 'user-abc-123',
      'topic_key' => 'billing.invoice_paid',
      'channel' => 'email',
      'enabled' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  #[Test]
  public function it_can_list_preferences_for_actor()
  {
    $mockPreference = $this->createMockPreference();
    $mockCollection = new \Illuminate\Database\Eloquent\Collection([$mockPreference]);

    $this->preferenceServiceMock
      ->shouldReceive('listForActor')
      ->once()
      ->with(\Mockery::any())
      ->andReturn($mockCollection);

    $request = Request::create('/api/v1/preferences', 'GET');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new PreferenceController($this->preferenceServiceMock);
    $response = $controller->index($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonStructure([
        'data' => [
          '*' => ['topic_key', 'channel', 'enabled']
        ]
      ])
      ->assertJsonPath('data.0.topic_key', 'billing.invoice_paid');
  }

  #[Test]
  public function it_can_update_preferences_for_actor()
  {
    $payload = [
      'preferences' => [
        [
          'topic_key' => 'billing.invoice_paid',
          'channel' => 'email',
          'enabled' => false,
        ]
      ]
    ];

    $mockPreference = $this->createMockPreference();
    $mockPreference->enabled = false; // محاكاة التعديل
    $mockCollection = new \Illuminate\Database\Eloquent\Collection([$mockPreference]);

    $this->preferenceServiceMock
      ->shouldReceive('upsertForActor')
      ->once()
      ->with(\Mockery::any(), $payload['preferences'])
      ->andReturn($mockCollection);

    $request = UpdateNotificationPreferencesRequest::create('/api/v1/preferences', 'PUT', $payload);
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    // تزييف الـ Validator ليعيد الـ payload كاملًا ليتوافق مع الـ FormRequest داخليًا
    $validatorMock = \Mockery::mock(Validator::class);
    $validatorMock->shouldReceive('validated')
      ->once()
      ->andReturn($payload);
    $request->setValidator($validatorMock);

    $this->app->instance('request', $request);

    $controller = new PreferenceController($this->preferenceServiceMock);
    $response = $controller->update($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonPath('data.0.enabled', false);
  }
}
