<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetUsersByEmailsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'max:100'],

            /*
            | Deliberately no `exists:users,email`: the caller is resolving
            | which of a set of addresses it knows about, so an unknown one is
            | an expected answer ("not found"), not a validation failure. The
            | by-ids endpoint can afford `exists` because an unknown id there
            | is a programming error.
            */
            'emails.*' => ['email'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required' => 'The emails field is required.',
            'emails.array' => 'The emails field must be an array.',
            'emails.max' => 'At most 100 emails can be resolved in one call.',
            'emails.*.email' => 'Each entry must be a valid email address.',
        ];
    }
}
