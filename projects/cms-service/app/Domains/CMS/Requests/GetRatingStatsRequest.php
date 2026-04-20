<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetRatingStatsRequest extends FormRequest
{
  public function rules()
  {
    return [
      'rateable_type' => 'required|in:project,data',
      'rateable_id' => 'required|integer',
    ];
  }
}
