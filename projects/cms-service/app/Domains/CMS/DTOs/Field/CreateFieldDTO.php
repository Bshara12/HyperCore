<?php

namespace App\Domains\CMS\DTOs\Field;

use App\Domains\CMS\Requests\CreateFieldRequest;
use App\Models\DataType;
use App\Models\DataTypeField;

class CreateFieldDTO
{
  public function __construct(
    public int $data_type_id,
    public string $name,
    public string $type,
    public bool $required,
    public bool $translatable,
    public array $validation_rules,
    public array $settings,
    public int $sort_order,
  ) {}

  public static function fromRequest(CreateFieldRequest $request, DataType $dataType): self
  {
    return new self(
      data_type_id: $dataType->id,
      name: $request->name,
      type: $request->type,
      required: $request->boolean('required'),
      translatable: $request->boolean('translatable'),
      validation_rules: $request->input('validation_rules', []),
      settings: $request->input('settings', []),
      sort_order: $request->input('sort_order', 0),
    );
  }

  public static function fromRequestForUpdate(CreateFieldRequest $request, DataTypeField $field): self
  {
    return new self(
      data_type_id: $field->data_type_id,
      name: $request->name,
      type: $request->type ?? $field->type,
      required: $request->boolean('required', $field->required),
      translatable: $request->boolean('translatable', $field->translatable),
      validation_rules: $request->input('validation_rules', $field->validation_rules ?? []),
      settings: $request->input('settings', $field->settings ?? []),
      sort_order: $request->input('sort_order', $field->sort_order ?? 0),
    );
  }
}
