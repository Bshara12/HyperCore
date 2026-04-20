<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class FieldTypeFactory
{
  public static function make(string $type): FieldTypeStrategy
  {
    return match ($type) {
      'text' => new TextFieldStrategy(),
      'number' => new NumberFieldStrategy(),
      'boolean' => new BooleanFieldStrategy(),
      'select' => new SelectFieldStrategy(),
      'json' => new JsonFieldStrategy(),
      'relation' => new RelationFieldStrategy(),
      'file' => new FileFieldStrategy(),
      default => abort(422, "Unsupported field type '{$type}'."),
    };
  }
}
