<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class   ActivationOfferRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'is_active' => 'required|boolean'
    ];
  }
}
