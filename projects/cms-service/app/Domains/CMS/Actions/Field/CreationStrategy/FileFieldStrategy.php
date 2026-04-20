<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class FileFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'required',
    'nullable',
    'mimes',
    'max',
    'min',
  ];

  public function validateRules(array $rules): void
  {
    foreach ($rules as $rule) {
      $ruleName = explode(':', $rule)[0];

      if (!in_array($ruleName, $this->allowedRules, true)) {
        abort(422, "Rule '{$rule}' is not allowed for file field.");
      }
    }
  }

  public function normalizeSettings(array $settings): array
  {
    return [
      'multiple' => (bool)($settings['multiple'] ?? false),

      'allowed_types' => $settings['allowed_types'] ?? [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'mp4',
        'mov',
        'pdf',
        'docx',
        'xlsx',
        'zip'
      ],

      'max_size' => $settings['max_size'] ?? 20480, // 20MB
      'max_files' => $settings['max_files'] ?? null,
    ];
  }
}
