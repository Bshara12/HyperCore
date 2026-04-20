<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateRequest extends FormRequest
{
  public function rules()
  {
    return [
      'rateable_type' => 'required|in:project,data',
      'rateable_id' => 'required|integer',
      'rating' => 'required|integer|min:1|max:5',
      'review' => 'nullable|string'
    ];
  }
}
