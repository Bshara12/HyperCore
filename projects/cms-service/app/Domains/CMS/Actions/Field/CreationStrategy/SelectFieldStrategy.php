<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class SelectFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'required',
    'in',
  ];

  public function validateRules(array $rules): void
  {
    foreach ($rules as $rule) {
      $name = explode(':', $rule)[0];
      if (!in_array($name, $this->allowedRules, true)) {
        abort(422, "Rule '{$rule}' is not allowed for select field.");
      }
    }
  }

  public function normalizeSettings(array $settings): array
  {
    if (!isset($settings['options']) || !is_array($settings['options'])) {
      abort(422, "Select field requires 'options' array.");
    }

    return [
      'options' => $settings['options'],
      'default' => $settings['default'] ?? null,
      'multiple' => $settings['multiple'] ?? false,
    ];
  }
}
