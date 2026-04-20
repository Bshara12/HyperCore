<?php

namespace App\Domains\CMS\Services;

use Illuminate\Support\Str;

class SeoGeneratorService
{
    public function generate(array $values): array
    {
        $seo = [];

        foreach ($values as $fieldId => $langs) {
            foreach ($langs as $lang => $value) {
                if (is_string($value)) {
                    $seo[$lang]['meta_title'] ??= $value;
                    $seo[$lang]['slug'] ??= Str::slug($value);
                }
            }
        }

        return $seo;
    }
}
