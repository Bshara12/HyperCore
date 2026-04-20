<?php

namespace Tests\Unit\Domains\CMS\DTOs\Field;

use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Requests\CreateFieldRequest;
use App\Models\DataType;
use App\Models\DataTypeField;
use Tests\TestCase;

class CreateFieldDTOTest extends TestCase
{
  /** @test */
  public function it_creates_dto_from_constructor()
  {
    $dto = new CreateFieldDTO(
      data_type_id: 5,
      name: 'Title',
      type: 'text',
      required: true,
      translatable: false,
      validation_rules: ['required'],
      settings: ['max' => 255],
      sort_order: 3
    );

    $this->assertEquals(5, $dto->data_type_id);
    $this->assertEquals('Title', $dto->name);
    $this->assertEquals('text', $dto->type);
    $this->assertTrue($dto->required);
    $this->assertFalse($dto->translatable);
    $this->assertEquals(['required'], $dto->validation_rules);
    $this->assertEquals(['max' => 255], $dto->settings);
    $this->assertEquals(3, $dto->sort_order);
  }

  /** @test */
  public function it_creates_dto_from_request()
  {
    $dataType = new DataType();
    $dataType->id = 10;

    $request = CreateFieldRequest::create('/', 'POST', [
      'name' => 'Body',
      'type' => 'textarea',
      'required' => '1',
      'translatable' => '0',
      'validation_rules' => ['required', 'string'],
      'settings' => ['rows' => 5],
      'sort_order' => 2,
    ]);

    $dto = CreateFieldDTO::fromRequest($request, $dataType);

    $this->assertEquals(10, $dto->data_type_id);
    $this->assertEquals('Body', $dto->name);
    $this->assertEquals('textarea', $dto->type);
    $this->assertTrue($dto->required);
    $this->assertFalse($dto->translatable);
    $this->assertEquals(['required', 'string'], $dto->validation_rules);
    $this->assertEquals(['rows' => 5], $dto->settings);
    $this->assertEquals(2, $dto->sort_order);
  }

  /** @test */
  public function it_sets_default_values_in_from_request()
  {
    $dataType = new DataType();
    $dataType->id = 3;

    $request = CreateFieldRequest::create('/', 'POST', [
      'name' => 'Summary',
      'type' => 'text',
    ]);

    $dto = CreateFieldDTO::fromRequest($request, $dataType);

    $this->assertEquals(3, $dto->data_type_id);
    $this->assertEquals('Summary', $dto->name);
    $this->assertEquals('text', $dto->type);
    $this->assertFalse($dto->required);
    $this->assertFalse($dto->translatable);
    $this->assertEquals([], $dto->validation_rules);
    $this->assertEquals([], $dto->settings);
    $this->assertEquals(0, $dto->sort_order);
  }

  /** @test */
  public function it_creates_dto_from_request_for_update_with_fallbacks()
  {
    $field = new DataTypeField();
    $field->data_type_id = 7;
    $field->type = 'number';
    $field->required = true;
    $field->translatable = true;
    $field->validation_rules = ['numeric'];
    $field->settings = ['min' => 1];
    $field->sort_order = 4;

    $request = CreateFieldRequest::create('/', 'POST', [
      'name' => 'Updated Field',
      // type missing → fallback
      // required missing → fallback
      // translatable missing → fallback
      // validation_rules missing → fallback
      // settings missing → fallback
      // sort_order missing → fallback
    ]);

    $dto = CreateFieldDTO::fromRequestForUpdate($request, $field);

    $this->assertEquals(7, $dto->data_type_id);
    $this->assertEquals('Updated Field', $dto->name);
    $this->assertEquals('number', $dto->type);
    $this->assertTrue($dto->required);
    $this->assertTrue($dto->translatable);
    $this->assertEquals(['numeric'], $dto->validation_rules);
    $this->assertEquals(['min' => 1], $dto->settings);
    $this->assertEquals(4, $dto->sort_order);
  }

  /** @test */
  public function it_overrides_fallbacks_when_values_are_provided_in_update()
  {
    $field = new DataTypeField();
    $field->data_type_id = 1;
    $field->type = 'text';
    $field->required = false;
    $field->translatable = false;
    $field->validation_rules = ['string'];
    $field->settings = ['max' => 100];
    $field->sort_order = 1;

    $request = CreateFieldRequest::create('/', 'POST', [
      'name' => 'New Name',
      'type' => 'email',
      'required' => '1',
      'translatable' => '1',
      'validation_rules' => ['email'],
      'settings' => ['max' => 255],
      'sort_order' => 10,
    ]);

    $dto = CreateFieldDTO::fromRequestForUpdate($request, $field);

    $this->assertEquals('email', $dto->type);
    $this->assertTrue($dto->required);
    $this->assertTrue($dto->translatable);
    $this->assertEquals(['email'], $dto->validation_rules);
    $this->assertEquals(['max' => 255], $dto->settings);
    $this->assertEquals(10, $dto->sort_order);
  }
}
