<?php

namespace App\Domains\CMS\StrategyCheck;

class StringFieldValidator implements FieldValidator
{
    public function validate($value, array $fieldConfig): void
    {
        if (! is_string($value)) {
            $name = $fieldConfig['name'] ?? 'value';

            throw new \Exception("Field {$name} must be string.");
        }
    }
}
