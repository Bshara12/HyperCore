<?php

namespace App\Domains\CMS\StrategyCheck;

class NumberFieldValidator implements FieldValidator
{
  public function validate($value, array $fieldConfig): void
  {
    if (!is_numeric($value)) {
      throw new \Exception("Field {$fieldConfig['name']} must be numeric.");
    }
  }
}
