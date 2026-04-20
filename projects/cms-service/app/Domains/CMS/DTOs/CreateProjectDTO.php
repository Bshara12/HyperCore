<?php
namespace App\Domains\CMS\DTOs;

use App\Domains\CMS\Requests\CreateProjectRequest;

class CreateProjectDTO
{
    public function __construct(
        public string $name,
        public int $ownerId,
        public ?array $supportedLanguages,
        public ?array $enabledModules,
    ) {}

    public static function fromRequest(CreateProjectRequest $request): self
    {
        $user = $request->attributes->get('auth_user');
        $userId = null;

        if (is_array($user) && isset($user['id'])) {
            $userId = (int) $user['id'];
        }

        if (is_object($user) && isset($user->id)) {
            $userId = (int) $user->id;
        }

        if (!$userId) {
            abort(401, 'Unauthorized');
        }

        return new self(
            $request->name,
            $userId,
            $request->supported_languages,
            $request->enabled_modules
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'owner_id' => $this->ownerId,
            'supported_languages' => $this->supportedLanguages,
            'enabled_modules' => $this->enabledModules,
        ];
    }
}
