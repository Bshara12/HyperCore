<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetAvailabilityRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'availabilities'                => ['required', 'array', 'min:1'],
      'availabilities.*.day_of_week'  => ['required', 'integer', 'between:0,6'],
      'availabilities.*.start_time'   => ['required', 'date_format:H:i'],
      'availabilities.*.end_time'     => ['required', 'date_format:H:i', 'after:availabilities.*.start_time'],
      'availabilities.*.slot_duration' => ['required', 'integer', 'min:5'],
      'availabilities.*.is_active'    => ['nullable', 'boolean'],
    ];
  }

  public function messages(): array
  {
    return [
      'availabilities.*.end_time.after'         => 'End time must be after start time.',
      'availabilities.*.slot_duration.min'       => 'Slot duration must be at least 5 minutes.',
      'availabilities.*.day_of_week.between'     => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
    ];
  }
}
