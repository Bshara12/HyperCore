<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFieldRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name' => 'required|string|max:255',
      'type' => 'required|string|max:255',
      'required' => 'boolean|sometimes',
      'translatable' => 'boolean|sometimes',
      'validation_rules' => 'array|sometimes',
      'validation_rules.*' => 'string|max:255',
      'settings' => 'array|sometimes',
      'settings.relation_type' => 'string|sometimes',
      'settings.related_data_type_id' => 'integer|exists:data_types,id|sometimes',
      'settings.multiple' => 'boolean|sometimes',
      'sort_order' => 'integer|sometimes',
    ];
  }
}
