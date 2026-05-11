<?php

namespace App\Domains\Subscription\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class RenewSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'gateway' => [
                'required',
                'string'
            ],

            'payment_type' => [
                'required',
                'string',
                'in:full,installment'
            ],

            'auto_renew' => [
                'nullable',
                'boolean'
            ],

            'metadata' => [
                'nullable',
                'array'
            ]
        ];
    }
}