<?php

namespace App\Domains\CMS\Analytics\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the analytics filters.
 *
 * These values reach two places that both need them bounded: the SQL (an
 * unbounded limit becomes an unbounded result set) and the cache key (every
 * distinct value mints a new cache entry, so free-form input is a cache
 * flooding vector — 20 requests with distinct `from` values produced 40 rows).
 */
class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'period' => ['sometimes', 'in:daily,weekly,monthly'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.date_format' => 'The from field must be a date in Y-m-d format.',
            'to.date_format' => 'The to field must be a date in Y-m-d format.',
            'to.after_or_equal' => 'The to field must not be earlier than from.',
            'limit.max' => 'The limit field must not be greater than 100.',
        ];
    }
}
