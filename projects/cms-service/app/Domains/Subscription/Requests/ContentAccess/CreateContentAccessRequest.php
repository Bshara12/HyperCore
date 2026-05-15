<?php

namespace App\Domains\Subscription\Requests\ContentAccess;

use Illuminate\Foundation\Http\FormRequest;

class CreateContentAccessRequest extends FormRequest
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
            | Resolved internally: content_id → DataEntry → data_type.slug
            |
            | We validate exists:data_entries to return a clean 422
            | before the Action runs, instead of a cryptic DB error.
            |----------------------------------------------------------
            */
            'content_id' => [
                'required',
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

            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }
}