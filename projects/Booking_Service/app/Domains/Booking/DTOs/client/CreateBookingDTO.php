<?php

namespace App\Domains\Booking\DTOs\Client;

class CreateBookingDTO
{
  public function __construct(
    public int $resourceId,
    public int $userId,
    public string $userName,
    public int $projectId,
    public string $startAt,
    public string $endAt,
    public float $amount,
    public string $currency,
    public string $gateway,
    public ?string $gatewayToken,
  ) {}

  public static function fromRequest($request): self
  {
    $user = $request->attributes->get('auth_user');

    if (!$user) {
      throw new \Exception('Unauthenticated');
    }
    return new self(
      resourceId: $request->resource_id,
      userId: $user['id'],
      userName: $user['name'],
      projectId: $request->project_id ?? 1,
      startAt: $request->start_at,
      endAt: $request->end_at,
      amount: $request->amount,
      currency: $request->currency,
      gateway: $request->gateway,
      gatewayToken: $request->token,
    );
  }
}
