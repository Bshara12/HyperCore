<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOfferRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      // Collection
      'name' => ['required', 'string'],
      'type' => ['required', 'in:manual,dynamic'],
      'data_type_id' => ['required', 'integer'],

      'conditions' => ['nullable', 'array'],
      'conditions.*.field' => ['required_with:conditions'],
      'conditions.*.operator' => ['required_with:conditions'],
      'conditions.*.value' => ['required_with:conditions'],

      'conditions_logic' => ['nullable', 'in:and,or'],

      'description' => ['nullable', 'string'],
      'settings' => ['nullable', 'array'],

      // Offer
      'is_code_offer' => ['required', 'boolean'],
      'offer_duration' => ['required_if:is_code_offer,true', 'numeric'],
      'benefit_type' => ['required', 'string', 'in:percentage,fixed_amount,buy_x_get_y,quantity,total_price'],
      'benefit_config' => ['required', 'array'],
      'start_at' => ['nullable', 'date'],
      'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
      'is_active' => ['boolean'],
    ];
  }
}
