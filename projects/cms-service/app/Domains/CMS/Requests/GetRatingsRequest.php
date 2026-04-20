<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetRatingsRequest extends FormRequest
{
  public function rules()
  {
    return [
      'rateable_type' => 'required|in:project,data',
      'rateable_id' => 'required|integer',
      'per_page' => 'nullable|integer|min:1|max:50'
    ];
  }
}
