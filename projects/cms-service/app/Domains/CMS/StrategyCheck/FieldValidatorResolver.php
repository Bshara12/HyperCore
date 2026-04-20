<?php
namespace App\Domains\CMS\StrategyCheck;

class FieldValidatorResolver
{
    public function resolve(string $type): FieldValidator
    {
        return match ($type) {
            'number' => new NumberFieldValidator(),
            'string' => new StringFieldValidator(),
            'text' => new StringFieldValidator(),
            'textarea' => new StringFieldValidator(),
            'select' => new StringFieldValidator(),
            'relation' => new StringFieldValidator(),
            'json' => new class implements FieldValidator {
                public function validate($value, array $fieldConfig): void
                {
                    if (is_array($value)) {
                        return;
                    }

                    if (is_string($value)) {
                        json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return;
                        }
                    }

                    $name = $fieldConfig['name'] ?? 'json';
                    throw new \Exception("Field {$name} must be valid JSON.");
                }
            },
            'boolean' => new class implements FieldValidator {
                public function validate($value, array $fieldConfig): void
                {
                    if (is_bool($value)) {
                        return;
                    }

                    if (is_numeric($value) && in_array((int) $value, [0, 1], true)) {
                        return;
                    }

                    if (is_string($value) && in_array(strtolower($value), ['true', 'false', '0', '1'], true)) {
                        return;
                    }

                    $name = $fieldConfig['name'] ?? 'boolean';
                    throw new \Exception("Field {$name} must be boolean.");
                }
            },
            'file'   => new FileFieldValidator(),
            default => throw new \Exception("Unsupported field type: {$type}")
        };
    }
}
