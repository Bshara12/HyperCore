<?php

namespace Tests\Feature\Requests;

use App\Domains\CMS\Requests\CreateDataTypeRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CreateDataTypeRequestTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    // Temporary route for testing the FormRequest
    Route::post('/test-create-data-type', function (CreateDataTypeRequest $request) {
      return response()->json(['validated' => $request->validated()]);
    });
  }

  /** @test */
  public function it_requires_name()
  {
    $response = $this->postJson('/test-create-data-type', [
      'slug' => 'test-slug'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
  }

  /** @test */
  public function it_requires_slug()
  {
    $response = $this->postJson('/test-create-data-type', [
      'name' => 'Test'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['slug']);
  }

  /** @test */
  public function it_validates_successfully_with_valid_data()
  {
    $response = $this->postJson('/test-create-data-type', [
      'name' => 'Test',
      'slug' => 'test-slug',
      'description' => 'desc',
      'is_active' => true,
      'settings' => ['a' => 1]
    ]);

    $response->assertStatus(200);
    $response->assertJson([
      'validated' => [
        'name' => 'Test',
        'slug' => 'test-slug',
        'description' => 'desc',
        'is_active' => true,
        'settings' => ['a' => 1]
      ]
    ]);
  }

  /** @test */
  public function is_active_must_be_boolean()
  {
    $response = $this->postJson('/test-create-data-type', [
      'name' => 'Test',
      'slug' => 'test-slug',
      'is_active' => 'not-boolean'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
  }

  /** @test */
  public function settings_must_be_array()
  {
    $response = $this->postJson('/test-create-data-type', [
      'name' => 'Test',
      'slug' => 'test-slug',
      'settings' => 'not-array'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['settings']);
  }
}
