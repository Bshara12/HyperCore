<?php

namespace App\Domains\Subscription\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'plan_id' => [
                'required',
                'exists:subscription_plans,id'
            ],

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
                'boolean'
            ],

            'metadata' => [
                'nullable',
                'array'
            ]
        ];
    }
}