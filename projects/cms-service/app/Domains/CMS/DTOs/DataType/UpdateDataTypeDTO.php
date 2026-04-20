<?php

namespace App\Domains\CMS\DTOs\DataType;

use App\Domains\CMS\Requests\UpdateDataTypeRequest;

class UpdateDataTypeDTO
{
  public function __construct(
    public string $name,
    public string $slug,
    public ?string $description = null,
    public bool $is_active = true,
    public array $settings = []
  ) {}

  public static function fromRequest(UpdateDataTypeRequest $request): self
  {
    return new self(
      name: $request->input('name'),
      slug: $request->input('slug'),
      description: $request->input('description'),
      is_active: $request->boolean('is_active', true),
      settings: $request->input('settings') ?? []
    );
  }
}
