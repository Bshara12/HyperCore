<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'name' => 'sometimes|required|string|max:255',
      'supported_languages' => 'sometimes|array',
      'enabled_modules' => 'sometimes|array',
    ];
  }
}
