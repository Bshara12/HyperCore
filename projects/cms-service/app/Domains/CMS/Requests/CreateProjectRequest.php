<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'name' => 'required|string|max:255',
      'supported_languages' => 'nullable|array',
      'enabled_modules' => 'nullable|array',
    ];
  }
}
