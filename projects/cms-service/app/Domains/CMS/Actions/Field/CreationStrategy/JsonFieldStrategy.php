<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class JsonFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'json',
    'required',
    'nullable'
  ];

  public function validateRules(array $rules): void
  {
    foreach ($rules as $rule) {
      $ruleName = explode(':', $rule)[0];

      if (!in_array($ruleName, $this->allowedRules)) {
        abort(422, "Rule '{$rule}' is not allowed for JSON field.");
      }
    }
  }

  public function normalizeSettings(array $settings): array
  {
    return [
      'schema' => $settings['schema'] ?? null,
    ];
  }
}
