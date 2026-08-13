<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetUsersByIdsRequest extends FormRequest
{
  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'ids' => ['required', 'array'],
      'ids.*' => ['integer', 'exists:users,id'],
    ];
  }

  public function messages(): array
  {
    return [
      'ids.required' => 'The ids field is required.',
      'ids.array' => 'The ids field must be an array.',
      'ids.*.integer' => 'Each id must be an integer.',
      'ids.*.exists' => 'One or more selected users do not exist.',
    ];
  }
}
