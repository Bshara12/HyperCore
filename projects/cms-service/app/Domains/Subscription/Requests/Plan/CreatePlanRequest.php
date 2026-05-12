<?php

namespace App\Domains\Subscription\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanRequest extends FormRequest
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
                // 'exists:projects,id'
            ],

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'slug' => [
                'required',
                'string',
                'max:255'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'currency' => [
                'required',
                'string',
                'size:3'
            ],

            'duration_days' => [
                'required',
                'integer',
                'min:1'
            ],

            'is_active' => [
                'boolean'
            ],

            'metadata' => [
                'nullable',
                'array'
            ],

            'features' => [
                'array'
            ],

            'features.*.feature_key' => [
                'required',
                'string'
            ],

            'features.*.feature_type' => [
                'required',
                'string'
            ],

            'features.*.feature_value' => [
                'required'
            ]
        ];
    }
}