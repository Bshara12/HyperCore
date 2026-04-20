<?php

namespace Tests\Feature\Repositories;

use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Eloquent\FieldRepositoryEloquent;
use App\Models\DataType;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldRepositoryEloquentTest extends TestCase
{
  use RefreshDatabase;

  protected FieldRepositoryEloquent $repo;

  protected function setUp(): void
  {
    parent::setUp();
    $this->repo = new FieldRepositoryEloquent();
  }

  /** @test */
  public function it_ensures_field_name_is_unique()
  {
    $dataType = DataType::factory()->create();

    DataTypeField::factory()->create([
      'data_type_id' => $dataType->id,
      'name' => 'title'
    ]);

    $this->expectExceptionMessage("Field 'title' already exists for this Data-Type.");

    $this->repo->ensureFieldIsUnique($dataType->id, 'title');
  }

  /** @test */
  public function it_ensures_updated_field_name_is_unique()
  {
    $dataType = DataType::factory()->create();

    $field1 = DataTypeField::factory()->create([
      'data_type_id' => $dataType->id,
      'name' => 'title'
    ]);

    $field2 = DataTypeField::factory()->create([
      'data_type_id' => $dataType->id,
      'name' => 'body'
    ]);

    $this->expectExceptionMessage("Field 'title' already exists for this Data-Type.");

    $this->repo->ensureUpdatedFieldIsUnique($dataType->id, 'title', $field2->id);
  }

  /** @test */
  public function it_creates_a_field()
  {
    $dataType = DataType::factory()->create();

    $dto = new CreateFieldDTO(
      data_type_id: $dataType->id,
      name: 'title',
      type: 'text',
      required: true,
      translatable: false,
      validation_rules: ['string'],
      settings: ['max' => 255],
      sort_order: 1
    );

    $field = $this->repo->create($dto, ['max' => 255]);

    $this->assertDatabaseHas('data_type_fields', [
      'id' => $field->id,
      'data_type_id' => $dataType->id,
      'name' => 'title',
      'type' => 'text',
      'required' => true,
      'translatable' => false,
      'sort_order' => 1
    ]);

    $this->assertEquals(['max' => 255], $field->settings);
  }

  /** @test */
  public function it_updates_a_field()
  {
    $field = DataTypeField::factory()->create([
      'name' => 'title',
      'required' => true,
      'translatable' => false,
      'validation_rules' => ['string'],
      'settings' => ['max' => 255],
      'sort_order' => 1
    ]);

    $dto = new CreateFieldDTO(
      data_type_id: $field->data_type_id,
      name: 'updated',
      type: $field->type,
      required: false,
      translatable: true,
      validation_rules: ['required'],
      settings: ['min' => 1],
      sort_order: 5
    );

    $updated = $this->repo->update($dto, $field, ['min' => 1]);

    $this->assertEquals('updated', $updated->name);
    $this->assertFalse($updated->required);
    $this->assertTrue($updated->translatable);
    $this->assertEquals(['required'], $updated->validation_rules);
    $this->assertEquals(['min' => 1], $updated->settings);
    $this->assertEquals(5, $updated->sort_order);
  }

  /** @test */
  public function it_gets_fields_by_data_type()
  {
    $dataType = DataType::factory()->create();

    DataTypeField::factory()->count(3)->create([
      'data_type_id' => $dataType->id
    ]);

    $fields = $this->repo->getByDataType($dataType->id);

    $this->assertCount(3, $fields);
  }

  /** @test */
  public function it_soft_deletes_a_field()
  {
    $field = DataTypeField::factory()->create();

    $this->repo->delete($field);

    $this->assertSoftDeleted('data_type_fields', ['id' => $field->id]);
  }

  /** @test */
  public function it_restores_a_soft_deleted_field()
  {
    $field = DataTypeField::factory()->create();
    $field->delete();

    $this->repo->restore($field->id);

    $this->assertDatabaseHas('data_type_fields', [
      'id' => $field->id,
      'deleted_at' => null
    ]);
  }

  /** @test */
  public function it_force_deletes_a_field()
  {
    $field = DataTypeField::factory()->create();

    $this->repo->forceDelete($field->id);

    $this->assertDatabaseMissing('data_type_fields', ['id' => $field->id]);
  }
}
