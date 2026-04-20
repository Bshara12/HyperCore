<?php

namespace App\Domains\CMS\DTOs\DataType;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\CreateDataTypeRequest;
use Illuminate\Support\Str;

class CreateDataTypeDTO
{
  public function __construct(
    public string $project_id,
    public string $name,
    public string $slug,
    public ?string $description = null,
    public ?bool $is_active = true,
    public ?array $settings = null,
  ) {}

  public static function fromRequest(CreateDataTypeRequest $request): self
  {
    $project_key = app('currentProject')->public_id;

    $id = app(ProjectRepositoryInterface::class)->findByKey($project_key)->id;

    return new self(
      project_id: $id,
      name: $request->input('name'),
      slug: $request->input('slug') ?? Str::slug($request->input('name')),
      description: $request->input('description'),
      is_active: $request->input('is_active', true),
      settings: $request->input('settings')
    );
  }
}
