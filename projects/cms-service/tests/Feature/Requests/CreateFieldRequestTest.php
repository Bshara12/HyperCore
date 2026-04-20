<?php

namespace Tests\Feature\Requests;

use App\Domains\CMS\Requests\CreateFieldRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\DataType;
use Tests\TestCase;

class CreateFieldRequestTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    // Temporary route for testing the FormRequest
    Route::post('/test-create-field', function (CreateFieldRequest $request) {
      return response()->json(['validated' => $request->validated()]);
    });
  }

  /** @test */
  public function it_requires_name()
  {
    $response = $this->postJson('/test-create-field', [
      'type' => 'text'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
  }

  /** @test */
  public function it_requires_type()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
  }

  /** @test */
  public function required_and_translatable_must_be_boolean()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'required' => 'not-boolean',
      'translatable' => 'not-boolean'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['required', 'translatable']);
  }

  /** @test */
  public function validation_rules_must_be_array()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'validation_rules' => 'not-array'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['validation_rules']);
  }

  /** @test */
  public function validation_rules_items_must_be_strings()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'validation_rules' => [123]
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['validation_rules.0']);
  }

  /** @test */
  public function settings_must_be_array()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'settings' => 'not-array'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['settings']);
  }

  /** @test */
  public function settings_relation_type_must_be_string()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'settings' => [
        'relation_type' => 123
      ]
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['settings.relation_type']);
  }

  /** @test */
  public function settings_related_data_type_id_must_exist()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'settings' => [
        'related_data_type_id' => 9999
      ]
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['settings.related_data_type_id']);
  }

  /** @test */
  public function settings_multiple_must_be_boolean()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'settings' => [
        'multiple' => 'not-boolean'
      ]
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['settings.multiple']);
  }

  /** @test */
  public function sort_order_must_be_integer()
  {
    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'sort_order' => 'not-integer'
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['sort_order']);
  }

  /** @test */
  public function it_validates_successfully_with_valid_data()
  {
    $dataType = DataType::factory()->create();

    $response = $this->postJson('/test-create-field', [
      'name' => 'Title',
      'type' => 'text',
      'required' => true,
      'translatable' => false,
      'validation_rules' => ['string', 'max:255'],
      'settings' => [
        'relation_type' => 'belongsTo',
        'related_data_type_id' => $dataType->id,
        'multiple' => false
      ],
      'sort_order' => 3
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['validated']);
  }
}
