<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateCollectionRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'is_active' => 'required|boolean'
    ];
  }
}
