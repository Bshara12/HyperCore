<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDataCollectionRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string'],
      'slug' => ['required', 'string'],
      'type' => ['required', 'in:manual,dynamic'],

      'conditions' => ['nullable', 'array'],
      'conditions.*.field' => ['required_with:conditions'],
      'conditions.*.operator' => ['required_with:conditions'],
      'conditions.*.value' => ['required_with:conditions'],

      'conditions_logic' => ['nullable', 'in:and,or'],

      'description' => ['nullable', 'string'],
      'is_active' => ['boolean'],
      'settings' => ['nullable', 'array'],
    ];
  }
}
