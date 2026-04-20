<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveCollectionItemsRequest extends FormRequest
{

  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'items' => 'required|array',
      'items.*' => [
        'required',
        'integer',
        'distinct',
        'exists:data_entries,id'
      ],
    ];
  }

  public function messages(): array
  {
    return [
      'items.required' => 'The items field is required.',
      'items.array' => 'The items field must be an array.',

      'items.*.required' => 'Each item must have an item_id.',
      'items.*.integer' => 'The item_id must be a valid integer.',
      'items.*.distinct' => 'Duplicate item_id values are not allowed.',
      'items.*.exists' => 'One or more item_id values do not exist in the database.',
    ];
  }
}
