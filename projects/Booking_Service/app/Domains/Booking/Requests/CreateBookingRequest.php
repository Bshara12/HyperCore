<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'resource_id' => 'required|exists:resources,id',
            'start_at'    => 'required|date',
            'end_at'      => 'required|date|after:start_at',
            'amount'      => 'required|numeric|min:0',
            'currency'    => 'required|string',
            'gateway'     => 'required|string',
            'token'       => 'nullable|string',
        ];
    }
}