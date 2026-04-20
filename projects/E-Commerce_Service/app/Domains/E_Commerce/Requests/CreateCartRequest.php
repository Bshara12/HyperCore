<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCartRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'items' => ['required', 'array', 'min:1'],

      'items.*.item_id' => ['required', 'integer'],
      'items.*.quantity' => ['required', 'integer', 'min:1'],
    ];
  }
}
