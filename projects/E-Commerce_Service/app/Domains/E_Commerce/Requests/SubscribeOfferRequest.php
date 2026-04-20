<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeOfferRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'code' => 'required'
    ];
  }
}
