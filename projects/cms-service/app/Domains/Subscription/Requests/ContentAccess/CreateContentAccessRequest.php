<?php

namespace App\Domains\Subscription\Requests\ContentAccess;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateContentAccessRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [

      'project_id' => [
        'nullable',
        'exists:projects,id'
      ],

      'content_type' => [
        'required',
        'string',
        'max:100'
      ],

      'content_id' => [

        'required',

        'integer',

        Rule::unique('content_access_metadata')
          ->where(function ($query) {

            return $query
              ->where('project_id', request('project_id'))
              ->where('content_type', request('content_type'));
          })
      ],

      'requires_subscription' => [
        'required',
        'boolean'
      ],

      'required_feature' => [
        'nullable',
        'string',
        'max:100'
      ],

      'metadata' => [
        'nullable',
        'array'
      ],

      Rule::unique('content_access_metadata')
        ->where(function ($query) {

          return $query
            ->where('project_id', request('project_id'))
            ->where('content_type', request('content_type'))
            ->where('content_id', request('content_id'));
        })
    ];
  }
}
