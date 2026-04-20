<?php

namespace App\Domains\CMS\DTOs\Project;

use App\Domains\CMS\Requests\UpdateProjectRequest;

class UpdateProjectDTO
{
  public function __construct(
    public ?string $name,
    public ?array $supportedLanguages,
    public ?array $enabledModules,
  ) {}

  public static function fromRequest(UpdateProjectRequest $request): self
  {
    return new self(
      $request->input('name'),
      $request->input('supported_languages'),
      $request->input('enabled_modules'),
    );
  }

  public function toArray(): array
  {
    return array_filter([
      'name' => $this->name,
      'supported_languages' => $this->supportedLanguages,
      'enabled_modules' => $this->enabledModules,
    ], fn($v) => !is_null($v));
  }
}
