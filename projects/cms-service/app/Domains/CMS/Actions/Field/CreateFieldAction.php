<?php

namespace App\Domains\CMS\Actions\Field;

use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\DataType;
use App\Models\DataTypeField;
use App\Models\DataTypeRelation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreateFieldAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataTypeField.create';
  }

  public function __construct(protected FieldRepositoryInterface $repository, protected FieldTypeFactory $factory) {}

  public function execute(CreateFieldDTO $dto): DataTypeField
  {
    return $this->run(function () use ($dto) {
      $this->repository->ensureFieldIsUnique($dto->data_type_id, $dto->name);
      $strategy = $this->factory->make($dto->type);
      $strategy->validateRules($dto->validation_rules);
      $normalizedSettings = $strategy->normalizeSettings($dto->settings);
      if ($dto->type === 'relation') {
        $normalizedSettings['data_type_relation_id'] = $this->ensureDataTypeRelationExists($dto, $normalizedSettings);
      }
      $field = $this->repository->create($dto, $normalizedSettings);

      Cache::forget(CacheKeys::fields($dto->data_type_id));

      // @codeCoverageIgnoreStart
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'create_field',
        userId: null,
        entityType: 'field',
        entityId: null
      ));
      // @codeCoverageIgnoreEnd

      return $field;

      // return $this->repository->create($dto, $normalizedSettings);
    });
  }

  public function ensureDataTypeRelationExists(CreateFieldDTO $dto, array $settings): int
  {
    /*
     | A relation must stay inside one project.
     |
     | Checking only that the target exists was an existence check, not an
     | ownership check: any caller able to name a data_type id could point a
     | field at another tenant's type. Resolve the owning project from the
     | field's own data type and require the target to share it.
     */
    $ownerProjectId = DataType::where('id', $dto->data_type_id)->value('project_id');

    if (! $ownerProjectId) {
      abort(422, 'DataType does not exist.');
    }

    $relatedDataType = DataType::where('id', $settings['related_data_type_id'])
      ->where('project_id', $ownerProjectId)
      ->first();

    if (! $relatedDataType) {
      abort(422, 'Related DataType does not exist in this project.');
    }


    $relation = DataTypeRelation::firstOrCreate([
      'data_type_id' => $dto->data_type_id,
      'related_data_type_id' => $relatedDataType->id,
      'relation_type' => $dto->settings['relation_type'],
      'relation_name' => $dto->settings['relation_name'] ?? Str::snake($relatedDataType->name),
    ]);

    return $relation->id;
  }
}
