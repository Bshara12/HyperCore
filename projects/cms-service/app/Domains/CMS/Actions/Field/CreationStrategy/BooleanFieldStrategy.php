<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class BooleanFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'boolean',
    'required'
  ];

  public function validateRules(array $rules): void
  {
    foreach ($rules as $rule) {
      $ruleName = explode(':', $rule)[0];

      if (!in_array($ruleName, $this->allowedRules)) {
        abort(422, "Rule '{$rule}' is not allowed for boolean field.");
      }
    }
  }

  public function normalizeSettings(array $settings): array
  {
    return [
      'default' => (bool)($settings['default'] ?? false),
    ];
  }
}
