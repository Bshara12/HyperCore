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

            // A field can be present and still carry no value: the global
            // ConvertEmptyStringsToNull middleware rewrites every empty input
            // to null before it reaches us, and isset() is true for a key
            // holding null. So "present" is decided by the values, not the key.
            if ($enforceRequired && $field->required && ! $this->hasValue($values, $slug)) {
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

            foreach ((array) $values[$slug] as $lang => $value) {

                // An empty value means "no value", not a value of the wrong
                // type. Required fields were already rejected above, so here it
                // can only be an optional field the user left blank or cleared.
                if ($this->isBlank($value)) {
                    continue;
                }

                $validator = $this->validatorResolver->resolve($field->type);

                $validator->validate($value, $fieldConfig);
            }
        }
    }

    /**
     * Whether the payload carries an actual value for the field in at least
     * one language.
     */
    private function hasValue(array $values, string $slug): bool
    {
        if (! isset($values[$slug])) {
            return false;
        }

        foreach ((array) $values[$slug] as $value) {
            if (! $this->isBlank($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Empty by absence of content only. "0", 0 and false are real values and
     * must not be treated as blank the way empty() would.
     */
    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
