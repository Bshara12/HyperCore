<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
{
  public function rules()
  {
    return [
      'items' => ['required', 'array'],
      'items.*.product_id' => ['required', 'integer'],
      'items.*.quantity' => ['required', 'integer', 'min:1'],
    ];
  }

  public function items()
  {
    return $this->input('items');
  }
}
