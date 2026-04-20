<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnRequestRequest extends FormRequest
{
  public function rules()
  {
    return [
      'order_id' => ['required', 'exists:orders,id'],
      'order_item_id' => ['required', 'exists:order_items,id'],
      'description' => ['nullable', 'string'],
      'quantity' => ['nullable', 'integer', 'min:1'],
    ];
  }
}