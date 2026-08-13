<?php

namespace App\Domains\Subscription\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;

class ListPlansRequest extends FormRequest
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
                'integer',
            ],
        ];
    }
}