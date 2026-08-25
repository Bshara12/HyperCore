<?php

namespace App\Domains\CMS\Requests;

use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDataCollectionRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'name' => ['required', 'string'],
      // 'slug' => ['required', 'string'],
      'type' => ['required', 'in:manual,dynamic'],

      'conditions' => ['nullable', 'array'],
      'conditions.*.field' => ['required_with:conditions'],
      'conditions.*.operator' => ['required_with:conditions'],
      'conditions.*.value' => ['required_with:conditions'],

      'conditions_logic' => ['nullable', 'in:and,or'],

      'description' => ['nullable', 'string'],
      'is_active' => ['boolean'],
      'settings' => ['nullable', 'array'],

      // 
      'data_type_id' => [
        'required',
        'integer',
        Rule::exists('data_types', 'id')->where('project_id', CurrentProject::id())
      ],
      'slug' => [
        'required',
        'string',
        'alpha_dash',
        'max:255',
        Rule::unique('data_collections', 'slug')->where('project_id', CurrentProject::id())
      ],

    ];
  }
}
