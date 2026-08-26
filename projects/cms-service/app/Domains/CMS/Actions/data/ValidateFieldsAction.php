<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\StrategyCheck\FieldValidatorResolver;
use DomainException;
use Illuminate\Database\Eloquent\Model;

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

            if ($enforceRequired && $field->required && ! isset($values[$slug])) {
                throw new DomainException("Field {$slug} is required.");
            }

            if (! isset($values[$slug])) {
                continue;
            }

            // (array) on an Eloquent model exposes its internals ("attributes",
            // "original", ...) instead of the columns, so the validators could
            // not read $fieldConfig['name'] and crashed while building their
            // own error message.
            $fieldConfig = $field instanceof Model ? $field->toArray() : (array) $field;

            foreach ($values[$slug] as $lang => $value) {

                $validator = $this->validatorResolver->resolve($field->type);

                $validator->validate($value, $fieldConfig);
            }
        }
    }
}
