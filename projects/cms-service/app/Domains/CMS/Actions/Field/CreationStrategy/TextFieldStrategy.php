<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

class TextFieldStrategy implements FieldTypeStrategy
{
  protected array $allowedRules = [
    'string',
    'max',
    'min',
    'required',
    'nullable',
    'email',      // ✅ للإيميل
    'url',        // ✅ للروابط
    'ip',         // ✅ لعناوين IP
    'uuid',       // ✅ للـ UUID
    'alpha',      // ✅ حروف فقط
    'alpha_num',  // ✅ حروف وأرقام
    'regex',      // ✅ للـ pattern المخصص
    'unique',     // ✅ للتفرد في DB
    'exists',     // ✅ للتحقق من وجود قيمة في DB
    'confirmed',  // ✅ للتأكيد (مثل password_confirmation)
    'different',  // ✅ يجب أن يكون مختلفاً عن حقل آخر
    'same',       // ✅ يجب أن يكون مطابقاً لحقل آخر
    'starts_with', // ✅ يبدأ بـ
    'ends_with',  // ✅ ينتهي بـ
    'in',         // ✅ ضمن قائمة (أحياناً للـ text)
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
