<?php

namespace App\Domains\CMS\StrategyCheck;

use Illuminate\Http\UploadedFile;

class FileFieldValidator implements FieldValidator
{
    public function validate($value, array $fieldConfig): void
    {
        $name = $fieldConfig['name'] ?? 'file';

        if (isset($fieldConfig['mimes'])) {
            $allowed = explode(',', $fieldConfig['mimes']);
            if (!in_array($value->getClientOriginalExtension(), $allowed)) {
                throw new \Exception("Field {$name} must be one of the following types: " . implode(', ', $allowed));
            }
        }

        if (isset($fieldConfig['max']) && $value->getSize() > $fieldConfig['max']) {
            throw new \Exception("Field {$name} exceeds the maximum allowed size of {$fieldConfig['max']} bytes.");
        }
    }
}