<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectEntriesRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'lang' => 'nullable|string',
      'page' => 'nullable|integer|min:1',
      'per_page' => 'nullable|integer|min:1|max:100',
      'search' => 'nullable|string',
      'field_id' => 'nullable|integer',
      'date_from' => 'nullable|date',
      'date_to' => 'nullable|date',
    ];
  }

  public function getFilters(): array
  {
    return [
      'lang' => $this->input('lang'),
      'page' => $this->input('page', 1),
      'per_page' => $this->input('per_page', 20),
      'search' => $this->input('search'),
      'field_id' => $this->input('field_id'),
      'date_from' => $this->input('date_from'),
      'date_to' => $this->input('date_to'),
    ];
  }
}
