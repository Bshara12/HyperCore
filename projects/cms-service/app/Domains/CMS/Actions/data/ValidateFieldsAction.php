<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\StrategyCheck\FieldValidatorResolver;
use DomainException;

class ValidateFieldsAction
{
  public function __construct(
    private FieldRepositoryInterface $fieldsRepo,
    private FieldValidatorResolver $validatorResolver
  ) {}

  public function execute(int $dataTypeId, array $values, bool $enforceRequired = true): void
  {
    $fields = $this->fieldsRepo->getByDataType($dataTypeId);

    foreach ($fields as $slug => $field) {

      if ($enforceRequired && $field->required && !isset($values[$slug])) {
        throw new DomainException("Field {$slug} is required.");
      }

      if (!isset($values[$slug])) {
        continue;
      }

      foreach ($values[$slug] as $lang => $value) {

        $validator = $this->validatorResolver->resolve($field->type);

        $validator->validate($value, (array) $field);
      }
    }
  }
}
