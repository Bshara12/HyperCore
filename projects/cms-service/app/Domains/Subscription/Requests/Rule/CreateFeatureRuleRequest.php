<?php

namespace App\Domains\Subscription\Requests\Rule;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateFeatureRuleRequest
extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'project_id' => [
                'nullable',
                'exists:projects,id'
            ],

            'event_key' => [
                'required',
                'string',
                'max:255'
            ],

            'feature_key' => [
                'required',
                'string',
                'max:255'
            ],

            'action' => [
                'required',

                Rule::in([
                    'check',
                    'increment',
                    'both'
                ])
            ],

            'reset_type' => [
                'required',

                Rule::in([
                    'never',
                    'daily',
                    'monthly',
                    'yearly'
                ])
            ],

            'is_active' => [
                'boolean'
            ],

            'metadata' => [
                'nullable',
                'array'
            ]
        ];
    }
}