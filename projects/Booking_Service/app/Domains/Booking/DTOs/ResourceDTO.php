<?php

namespace App\Domains\Booking\DTOs;

use App\Domains\Booking\Requests\CreateResourceRequest;
use App\Domains\Booking\Requests\UpdateResourceRequest;
use App\Models\Resource;

class ResourceDTO
{
  public function __construct(
    public readonly ?int    $projectId    = null,
    public readonly ?int    $dataEntryId  = null,
    public readonly ?string $name         = null,
    public readonly ?string $type         = null,
    public readonly ?int    $capacity     = null,
    public readonly ?string $status       = null,
    public readonly ?string $paymentType  = null,
    public readonly ?float  $price        = null,
    public readonly ?array  $settings     = null,
  ) {}

  public static function fromCreateRequest(CreateResourceRequest $request): self
  {
    return new self(
      projectId: $request->project_id,
      dataEntryId: $request->data_entry_id,
      name: $request->name,
      type: $request->type,
      capacity: $request->capacity ?? 1,
      paymentType: $request->payment_type,
      price: $request->payment_type === 'paid' ? $request->price : null,
      settings: $request->settings ?? null,
    );
  }

  public static function fromUpdateRequest(UpdateResourceRequest $request): self
  {
    return new self(
      name: $request->name ? $request->name : null,
      type: $request->has('type') ? $request->type : null,
      capacity: $request->has('capacity') ? $request->capacity : null,
      status: $request->has('status') ? $request->status : null,
      paymentType: $request->has('payment_type') ? $request->payment_type : null,
      price: $request->has('price') ? $request->price : null,
      settings: $request->has('settings') ? $request->settings : null,
    );
  }

  public function toCreateArray(): array
  {
    return [
      'data_entry_id' => $this->dataEntryId,
      'project_id'    => $this->projectId,
      'name'          => $this->name,
      'type'          => $this->type,
      'capacity'      => $this->capacity ?? 1,
      'status'        => $this->status ?? Resource::STATUS_ACTIVE,
      'payment_type'  => $this->paymentType ?? Resource::PAYMENT_FREE,
      'price'         => $this->paymentType === Resource::PAYMENT_PAID ? $this->price : null,
      'settings'      => $this->settings,
    ];
  }

  public function toUpdateArray(): array
  {
    $data = array_filter([
      'data_entry_id' => $this->dataEntryId,
      'name'          => $this->name,
      'type'          => $this->type,
      'capacity'      => $this->capacity,
      'status'        => $this->status,
      'payment_type'  => $this->paymentType,
      'settings'      => $this->settings,
    ], fn($v) => $v !== null);

    if ($this->paymentType === Resource::PAYMENT_FREE) {
      $data['price'] = null;
    } elseif ($this->price !== null) {
      $data['price'] = $this->price;
    }

    return $data;
  }
}
