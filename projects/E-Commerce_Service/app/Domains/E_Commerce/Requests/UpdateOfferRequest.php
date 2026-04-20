<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOfferRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      // Collection
      'name' => ['nullable', 'string'],
      'conditions_logic' => ['nullable', 'in:and,or'],
      'conditions' => ['nullable', 'array'],
      'conditions.*.field' => ['required_with:conditions'],
      'conditions.*.operator' => ['required_with:conditions'],
      'conditions.*.value' => ['required_with:conditions'],
      'description' => ['nullable', 'string'],

      // Offer
      'offer_duration' => ['nullable', 'numeric'],
      'benefit_type' => ['nullable', 'string', 'in:percentage,fixed_amount,buy_x_get_y,quantity,total_price'],
      'benefit_config' => ['nullable', 'array'],
      'start_at' => ['nullable', 'date'],
      'end_at' => ['nullable', 'date', 'after_or_equal:start_at', 'required_with:start_at'],
    ];
  }

  public function withValidator($validator)
  {
    $validator->after(function ($validator) {

      $type = $this->benefit_type;
      $config = $this->benefit_config;

      if (!$type) {
        return;
      }

      if (!$config) {
        $validator->errors()->add('benefit_config', 'benefit_config is required when benefit_type is provided.');
        return;
      }

      switch ($type) {

        case 'percentage':
          if (!isset($config['percentage'])) {
            $validator->errors()->add('benefit_config.percentage', 'percentage is required for percentage benefit_type.');
          }
          break;

        case 'fixed_amount':
          if (!isset($config['fixed_amount'])) {
            $validator->errors()->add('benefit_config.fixed_amount', 'fixed_amount is required for fixed_amount benefit_type.');
          }
          break;

        case 'buy_x_get_y':
          foreach (['targeted_item', 'targeted_item_count', 'acquired_item', 'acquired_item_count'] as $field) {
            if (!isset($config[$field])) {
              $validator->errors()->add("benefit_config.$field", "$field is required for buy_x_get_y benefit_type.");
            }
          }
          break;

        case 'quantity':
          foreach (['quantity', 'discount_type', 'discount_value'] as $field) {
            if (!isset($config[$field])) {
              $validator->errors()->add("benefit_config.$field", "$field is required for quantity benefit_type.");
            }
          }

          if (
            isset($config['discount_type']) &&
            !in_array($config['discount_type'], ['percentage', 'fixed_amount'])
          ) {
            $validator->errors()->add('benefit_config.discount_type', 'discount_type must be percentage or fixed_amount.');
          }
          break;

        case 'total_price':
          foreach (['total_price', 'discount_type', 'discount_value'] as $field) {
            if (!isset($config[$field])) {
              $validator->errors()->add("benefit_config.$field", "$field is required for total_price benefit_type.");
            }
          }

          if (
            isset($config['discount_type']) &&
            !in_array($config['discount_type'], ['percentage', 'fixed_amount'])
          ) {
            $validator->errors()->add('benefit_config.discount_type', 'discount_type must be percentage or fixed_amount.');
          }
          break;
      }
    });
  }
}
