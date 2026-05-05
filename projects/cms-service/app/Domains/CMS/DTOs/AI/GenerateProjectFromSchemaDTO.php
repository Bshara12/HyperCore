<?php

namespace App\Domains\CMS\DTOs\AI;

use App\Domains\CMS\Requests\AIGenerateSchemaRequest;

class GenerateProjectFromSchemaDTO
{
  public function __construct(
    public readonly int    $ownerId,
    public readonly array  $projectInfo,
    public readonly array  $customDataTypes,
    public readonly array  $relations,
  ) {}

  public static function fromRequest(AIGenerateSchemaRequest $request)
  {
    return new self(
      ownerId: $request->attributes->get('auth_user')['id'],
      projectInfo: $request['project_info'],
      customDataTypes: $request['custom_data_types'] ?? [],
      relations: $request['relations'] ?? [],
    );
  }
}
