<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'booking_id' => 'required|exists:bookings,id',
      'start_at'   => 'required|date',
      'end_at'     => 'required|date|after:start_at',
    ];
  }
}
