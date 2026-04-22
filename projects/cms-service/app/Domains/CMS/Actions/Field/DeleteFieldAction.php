<?php

namespace App\Domains\CMS\Actions\Field;

use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\DataTypeField;
use Illuminate\Support\Facades\DB;

class DeleteFieldAction extends Action
{

  protected function circuitServiceName(): string
  {
    return 'dataTypeField.delete';
  }

  public function __construct(
    protected FieldRepositoryInterface $repository,
  ) {}

  public function execute(DataTypeField $field): void
  {
    $this->run(function () use ($field) {
      $this->repository->delete($field);
    });
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'delete_field',
        userId: null,
        entityType: 'field',
        entityId:$field->id??null
      ));
  }
}
