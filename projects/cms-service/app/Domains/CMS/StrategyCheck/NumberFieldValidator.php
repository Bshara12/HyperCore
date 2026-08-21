<?php

namespace App\Domains\CMS\StrategyCheck;

class NumberFieldValidator implements FieldValidator
{
    public function validate($value, array $fieldConfig): void
    {
        if (! is_numeric($value)) {
            $name = $fieldConfig['name'] ?? 'value';

            throw new \Exception("Field {$name} must be numeric.");
        }
    }
}
