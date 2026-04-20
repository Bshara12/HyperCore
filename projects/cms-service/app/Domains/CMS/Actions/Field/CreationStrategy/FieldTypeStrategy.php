<?php

namespace App\Domains\CMS\Actions\Field\CreationStrategy;

interface FieldTypeStrategy
{
  public function validateRules(array $rules): void;

  public function normalizeSettings(array $settings): array;
}
