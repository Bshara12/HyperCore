<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePricingRequest extends FormRequest
{
    public function rules()
    {
        return [
            'entry_ids' => 'required|array',
            'entry_ids.*' => 'integer',
            'code' => 'nullable|string'
        ];
    }
}