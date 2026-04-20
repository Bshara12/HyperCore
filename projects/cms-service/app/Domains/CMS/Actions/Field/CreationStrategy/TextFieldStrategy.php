<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class TextFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'string',
    'max',
    'min',
    'required',
    'nullable'
  ];

  public function validateRules(array $rules): void
  {
    foreach ($rules as $rule) {
      $ruleName = explode(':', $rule)[0];

      if (!in_array($ruleName, $this->allowedRules)) {
        abort(422, "Rule '{$rule}' is not allowed for text field.");
      }
    }
  }

  public function normalizeSettings(array $settings): array
  {
    return [
      'placeholder' => $settings['placeholder'] ?? null,
      'default' => $settings['default'] ?? null,
    ];
  }
}
