<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetUsersByIdsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'The ids field is required.',
            'ids.array' => 'The ids field must be an array.',
            'ids.min' => 'At least one id is required.',
            'ids.*.integer' => 'Each id must be an integer.',
            'ids.*.exists' => 'One or more selected users do not exist.',
        ];
    }
}
