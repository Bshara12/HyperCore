<?php

namespace App\Domains\CMS\Requests;

use App\Models\DataCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InsertCollectionItemsRequest extends FormRequest
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
        'exists:data_entries,id',
        // Rule::unique('data_collection_items', 'item_id')
        //   ->where(function ($query) {
        //     return $query->where('collection_id', $this->getCurrentCollectionId());
        //   }),
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

      'items.*.sort_order.integer' => 'The sort_order must be a valid integer.',
      'items.*.sort_order.distinct' => 'Duplicate sort_order values are not allowed.',
      'items.*.sort_order.unique' => 'This sort_order is already used in this collection.',
    ];
  }

  private function getCurrentCollectionId()
  {
    return DataCollection::where('slug', $this->route('collectionSlug'))->value('id');
  }
}
