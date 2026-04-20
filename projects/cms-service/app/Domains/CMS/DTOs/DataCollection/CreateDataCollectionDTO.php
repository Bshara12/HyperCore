<?php

namespace App\Domains\CMS\DTOs\DataCollection;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\CreateDataCollectionRequest;

class CreateDataCollectionDTO
{
  public function __construct(
    public int $project_id,
    public int $data_type_id,
    public string $name,
    public string $slug,
    public string $type,
    public ?array $conditions,
    public ?string $conditions_logic,
    public ?string $description,
    public bool $is_active,
    public bool $is_offer,
    public ?array $settings,
  ) {}

  public static function fromRequest(CreateDataCollectionRequest $request): self
  {
    $project_key = app('currentProject')->public_id;

    $id = app(ProjectRepositoryInterface::class)->findByKey($project_key)->id;

    return new self(
      project_id: $id,
      data_type_id: $request->data_type_id,
      name: $request->name,
      slug: $request->slug,
      type: $request->type,
      conditions: $request->conditions,
      conditions_logic: $request->conditions_logic ?? 'and',
      description: $request->description,
      is_active: $request->is_active ?? true,
      is_offer: $request->is_active ?? false,
      settings: $request->settings,
    );
  }

  public function CollectionToArray(): array
  {
    return [
      'project_id' => $this->project_id,
      'data_type_id' => $this->data_type_id,
      'name' => $this->name,
      'slug' => $this->slug,
      'type' => $this->type,
      'conditions' => $this->conditions,
      'conditions_logic' => $this->conditions_logic,
      'description' => $this->description,
      'is_active' => $this->is_active,
      'is_offer' => $this->is_offer,
      'settings' => $this->settings,
    ];
  }
}
