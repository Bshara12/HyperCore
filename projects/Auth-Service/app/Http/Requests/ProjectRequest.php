<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      // 'owner_id' => 'numeric|required',
      'name' => 'required|string|max:255',
      'slug' => 'string|required|max:255',
      'is_active' => 'boolean|required',
      'settings' => 'nullable|json',
    ];
  }
}
