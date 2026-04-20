<?php

namespace App\Domains\E_Commerce\DTOs\Offers;

use App\Domains\E_Commerce\Requests\CreateOfferRequest;
use Illuminate\Support\Str;

class CreateOfferDTO
{
  public function __construct(
    public int $project_id,
    public int $data_type_id,
    public string $name,
    public string $slug,
    public string $type,
    public ?array $conditions,
    public ?string $conditions_logic,
    public ?string $description,
    public ?array $settings,
    public bool $is_code_offer,
    public ?int $offer_duration,
    public string $benefit_type,
    public array $benefit_config,
    public ?string $start_at,
    public ?string $end_at,
    public ?bool $is_active,
  ) {}

  public static function fromRequest(CreateOfferRequest $request): self
  {
    $slug = Str::slug($request->name);

    return new self(
      $request->project_id,
      $request->data_type_id,
      $request->name,
      $slug,
      $request->type,
      $request->conditions,
      $request->conditions_logic ?? 'and',
      $request->description,
      $request->settings,
      $request->is_code_offer,
      $request->offer_duration ?? null,
      $request->benefit_type,
      $request->benefit_config,
      $request->start_at,
      $request->end_at,
      $request->is_active ?? true
    );
  }

  public function CollectionToArray(): array
  {
    return [
      'project_id' => $this->project_id,
      'data_type_id' => $this->data_type_id,
      'name' => $this->name,
      'slug' => $this->slug,
      'type' => $this->type,
      'conditions' => $this->conditions,
      'conditions_logic' => $this->conditions_logic,
      'description' => $this->description,
      'is_active' => true,
      'is_offer' => true,
      'settings' => $this->settings
    ];
  }

  public function OfferToArray(): array
  {
    return [
      'project_id' => $this->project_id,
      'is_code_offer' => $this->is_code_offer,
      'offer_duration' => $this->offer_duration ?? null,
      'code' => $this->is_code_offer
        ? collect(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*'))
        ->random(8)
        ->implode('')
        : null,
      'benefit_type' => $this->benefit_type,
      'benefit_config' => $this->benefit_config,
      'start_at' => $this->start_at ?? null,
      'end_at' => $this->end_at ?? null,
      'is_active' => $this->is_active ?? true
    ];
  }
}
