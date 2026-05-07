<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkAsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_type' => ['required', 'string'],
            'recipient_id' => ['required'],
            'project_id' => ['nullable', 'string'],
        ];
    }
}
