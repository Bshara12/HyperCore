<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'booking_id' => 'required|exists:bookings,id'
        ];
    }
}