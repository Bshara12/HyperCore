<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDataTypeRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name' => 'required|string|max:255',
      'slug' => 'required|string|max:255',
      'description' => 'nullable|string',
      'is_active' => 'sometimes|boolean',
      'settings' => 'sometimes|array'
    ];
  }
}
