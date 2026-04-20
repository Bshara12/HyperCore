<?php

namespace App\Domains\CMS\StrategyCheck;

interface FieldValidator
{
  public function validate($value, array $fieldConfig): void;
}
