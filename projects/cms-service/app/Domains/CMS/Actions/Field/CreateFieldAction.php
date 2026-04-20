<?php

namespace App\Domains\CMS\Actions\Field;

use App\Domains\Core\Actions\Action;
use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Models\DataType;
use App\Models\DataTypeField;
use App\Models\DataTypeRelation;

class CreateFieldAction extends Action
{

  protected function circuitServiceName(): string
  {
    return 'dataTypeField.create';
  }

  public function __construct(protected FieldRepositoryInterface $repository) {}

  public function execute(CreateFieldDTO $dto): DataTypeField
  {
    return $this->run(function () use ($dto) {
      $this->repository->ensureFieldIsUnique($dto->data_type_id, $dto->name);
      $strategy = FieldTypeFactory::make($dto->type);
      $strategy->validateRules($dto->validation_rules);
      $normalizedSettings = $strategy->normalizeSettings($dto->settings);
      if ($dto->type === 'relation') {
        $normalizedSettings['data_type_relation_id'] = $this->ensureDataTypeRelationExists($dto, $normalizedSettings);
      }
      return $this->repository->create($dto, $normalizedSettings);
    });
  }

  public function ensureDataTypeRelationExists(CreateFieldDTO $dto, array $settings): int
  {
    $relatedDataType = DataType::find($settings['related_data_type_id']);

    if (!$relatedDataType) {
      abort(422, "Related DataType does not exist.");
    }

    $relationType = match ($settings['relation_type']) {
      'belongs_to' => 'many_to_one',
      'has_many' => 'one_to_many',
      'many_to_many' => 'many_to_many',
      default => abort(422, "Invalid relation_type for DataTypeRelation."),
    };

    $relation = DataTypeRelation::firstOrCreate([
      'data_type_id' => $dto->data_type_id,
      'related_data_type_id' => $relatedDataType->id,
      'relation_type' => $relationType,
    ]);

    return $relation->id;
  }
}
