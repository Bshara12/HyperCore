<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class NumberFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'numeric',
    'integer',
    'min',
    'max',
    'required',
    'nullable'
  ];

  public function validateRules(array $rules): void
  {
    foreach ($rules as $rule) {
      $ruleName = explode(':', $rule)[0];

      if (!in_array($ruleName, $this->allowedRules)) {
        abort(422, "Rule '{$rule}' is not allowed for number field.");
      }
    }
  }

  public function normalizeSettings(array $settings): array
  {
    return [
      'default' => $settings['default'] ?? null,
      'step' => $settings['step'] ?? 1,
    ];
  }
}
