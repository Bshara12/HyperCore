<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\DataCollection;

class UpdateDataCollectionRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'name' => 'sometimes|string|max:255',

      'conditions' => 'sometimes|array',
      'conditions.*.field' => 'required_with:conditions|string',
      'conditions.*.operator' => 'required_with:conditions|string',
      'conditions.*.value' => 'required_with:conditions',

      'conditions_logic' => 'sometimes|in:and,or',

      'description' => 'sometimes|string',

      'settings' => 'sometimes|array',
    ];
  }

  public function withValidator($validator)
  {
    $validator->after(function ($validator) {

      $collection = DataCollection::where('slug', $this->route('collectionSlug'))->first();

      if (!$collection) {
        return;
      }

      if ($collection->type === 'manual' && $this->has('conditions')) {
        $validator->errors()->add(
          'conditions',
          'You cannot send conditions for a manual collection.'
        );
      }
    });
  }
}
