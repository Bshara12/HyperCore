<?php

namespace App\Domains\AI\DTOs;

class ProvisionProjectFromSchemaDTO
{
  public function __construct(
    public readonly int    $ownerId,
    public readonly array  $projectInfo,       // name, slug, languages, modules, description
    public readonly array  $customDataTypes,   // كل الـ custom data types مع fields
    public readonly array  $relations,         // العلاقات بين الـ data types
  ) {}

  public static function fromRequest(array $data, int $ownerId): self
  {
    return new self(
      ownerId: $ownerId,
      projectInfo: $data['project_info'],
      customDataTypes: $data['custom_data_types'] ?? [],
      relations: $data['relations']         ?? [],
    );
  }
}
