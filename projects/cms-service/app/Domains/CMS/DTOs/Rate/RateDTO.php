<?php

namespace App\Domains\CMS\DTOs\Rate;

class RateDTO
{
  public function __construct(
    public int $userId,
    public string $rateableType,
    public int $rateableId,
    public int $rating,
    public ?string $review
  ) {}

  public static function fromRequest($request): self
  {
    $user = $request->attributes->get('auth_user');
    if (!$user) {
      throw new \Exception('Unauthenticated');
    }
    return new self(
      userId: $user['id'],
      rateableType: $request->rateable_type,
      rateableId: $request->rateable_id,
      rating: $request->rating,
      review: $request->review
    );
  }
}
