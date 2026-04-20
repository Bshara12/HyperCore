<?php

namespace App\Domains\E_Commerce\DTOs\Offers;

use App\Domains\E_Commerce\Requests\SubscribeOfferRequest;

class SubscribeDTO
{
  public function __construct(
    public string $collectionSlug,
    public string $code,
    public int $user_id,
    public int $project_id
  ) {}

  public static function fromRequest(string $collectionSlug, SubscribeOfferRequest $request): self
  {
    return new self(
      collectionSlug: $collectionSlug,
      code: $request->code,
      user_id: $request->attributes->get('auth_user')['id'],
      project_id: $request->project_id,
    );
  }
}
