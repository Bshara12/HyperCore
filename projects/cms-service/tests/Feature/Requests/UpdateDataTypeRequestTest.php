<?php

namespace Tests\Feature\Requests;

use App\Domains\CMS\Requests\UpdateDataTypeRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UpdateDataTypeRequestTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    // Temporary route for testing the FormRequest
    Route::put('/test-update-data-type', function (UpdateDataTypeRequest $request) {
      return response()->json(['validated' => $request->validated()]);
    });
  }

  /** @test */
  public function it_requires_name()
  {
    $response = $this->putJson('/test-update-data-type', [
      'slug' => 'test-slug'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
  }

  /** @test */
  public function it_requires_slug()
  {
    $response = $this->putJson('/test-update-data-type', [
      'name' => 'Test'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['slug']);
  }

  /** @test */
  public function is_active_must_be_boolean()
  {
    $response = $this->putJson('/test-update-data-type', [
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
    $response = $this->putJson('/test-update-data-type', [
      'name' => 'Test',
      'slug' => 'test-slug',
      'settings' => 'not-array'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['settings']);
  }

  /** @test */
  public function it_validates_successfully_with_valid_data()
  {
    $response = $this->putJson('/test-update-data-type', [
      'name' => 'Updated Name',
      'slug' => 'updated-slug',
      'description' => 'desc',
      'is_active' => true,
      'settings' => ['a' => 1]
    ]);

    $response->assertStatus(200);
    $response->assertJson([
      'validated' => [
        'name' => 'Updated Name',
        'slug' => 'updated-slug',
        'description' => 'desc',
        'is_active' => true,
        'settings' => ['a' => 1]
      ]
    ]);
  }
}
