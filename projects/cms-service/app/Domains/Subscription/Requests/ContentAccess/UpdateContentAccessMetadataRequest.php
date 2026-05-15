<?php

namespace App\Domains\Subscription\Requests\ContentAccess;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentAccessMetadataRequest extends FormRequest
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
                'exists:projects,id',
            ],

            /*
            |----------------------------------------------------------
            | content_type removed from API.
            | content_id is optional on update — if not provided,
            | the existing value on the model is kept as-is.
            | If provided, must exist in data_entries.
            |----------------------------------------------------------
            */
            'content_id' => [
                'nullable',
                'integer',
                'exists:data_entries,id',
            ],

            'requires_subscription' => [
                'required',
                'boolean',
            ],

            'features' => [
                'nullable',
                'array',
            ],

            'features.*' => [
                'string',
                'max:100',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }
}