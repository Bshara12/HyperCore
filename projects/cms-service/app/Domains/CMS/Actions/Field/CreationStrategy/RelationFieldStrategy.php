<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class RelationFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'required',
    'exists',
  ];

  public function validateRules(array $rules): void
  {
    foreach ($rules as $rule) {
      $name = explode(':', $rule)[0];
      if (!in_array($name, $this->allowedRules, true)) {
        abort(422, "Rule '{$rule}' is not allowed for relation field.");
      }
    }
  }

  public function normalizeSettings(array $settings): array
  {
    if (!isset($settings['relation_type'])) {
      abort(422, "Relation field requires 'relation_type'.");
    }

    if (!isset($settings['related_data_type_id'])) {
      abort(422, "Relation field requires 'related_data_type_id'.");
    }

    $multiple = match ($settings['relation_type']) {
      'belongs_to' => false,
      'has_many', 'many_to_many' => true,
      default => abort(422, "Invalid relation_type.")
    };

    return [
      'relation_type' => $settings['relation_type'],
      'related_data_type_id' => $settings['related_data_type_id'],
      'multiple' => $settings['multiple'] ?? $multiple,
      'data_type_relation_id' => $settings['data_type_relation_id'] ?? null,
    ];
  }
}
