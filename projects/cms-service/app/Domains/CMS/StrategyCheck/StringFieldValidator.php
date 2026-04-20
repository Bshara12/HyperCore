<?php

namespace  App\Domains\CMS\StrategyCheck;

class StringFieldValidator implements FieldValidator
{
    public function validate($value, array $fieldConfig): void
    {
        if (!is_string($value)) {
            throw new \Exception("Field {$fieldConfig['name']} must be string.");
        }
    }
}
