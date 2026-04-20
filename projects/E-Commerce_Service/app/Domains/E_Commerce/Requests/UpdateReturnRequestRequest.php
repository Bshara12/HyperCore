<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnRequestRequest extends FormRequest
{
  public function rules()
  {
    return [
      'status' => ['required', 'in:approved,rejected'],
    ];
  }
}
