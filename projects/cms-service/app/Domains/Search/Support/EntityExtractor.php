<?php

namespace App\Domains\Search\Support;

use App\Domains\Search\DTOs\SearchEntitiesDTO;

class EntityExtractor
{
    /*
     * قائمة العلامات التجارية الشائعة
     * قابلة للتوسيع من DB أو config
     */
    private const KNOWN_BRANDS = [
        // Tech
        'apple', 'samsung', 'google', 'microsoft', 'sony', 'lg',
        'huawei', 'xiaomi', 'oppo', 'vivo', 'oneplus', 'nokia',
        'dell', 'hp', 'lenovo', 'asus', 'acer', 'toshiba',
        // Cars
        'toyota', 'honda', 'bmw', 'mercedes', 'audi', 'ford',
        'hyundai', 'kia', 'nissan', 'volkswagen', 'chevrolet',
        // Arabic brands
        'ابل', 'سامسونج', 'هواوي', 'شاومي',
    ];

    /*
     * كلمات دالة على الموقع الجغرافي
     * يمكن توسيعها بقائمة دول كاملة
     */
    private const LOCATION_INDICATORS = [
        'in', 'at', 'near', 'from', 'around',
        'في', 'بـ', 'قرب', 'من',
    ];

    private const KNOWN_LOCATIONS = [
        'romania', 'egypt', 'saudi', 'uae', 'dubai', 'riyadh',
        'cairo', 'london', 'paris', 'berlin', 'usa', 'uk', 'germany',
        'مصر', 'السعودية', 'الإمارات', 'دبي', 'الرياض', 'القاهرة',
    ];

    /*
     * patterns لاستخراج الـ model numbers
     * مثل: 15 pro max, s24 ultra, pixel 8 pro
     */
    private const MODEL_PATTERNS = [
        '/\b(\d{1,2}(?:\s+(?:pro|max|ultra|plus|lite|mini|edge))+)\b/i',
        '/\b([a-z]\d+(?:\s+(?:pro|max|ultra|plus|lite))?)\b/i',
        '/\bseries\s+(\d+)\b/i',
    ];

    // ─────────────────────────────────────────────────────────────────

    /**
     * استخراج الـ entities من الـ query
     *
     * الخوارزمية:
     * 1. تطبيع النص
     * 2. كشف الـ brand
     * 3. كشف الـ model number
     * 4. بناء اسم المنتج (brand + model)
     * 5. كشف الـ location
     * 6. كشف نطاق السعر
     * 7. كشف attributes إضافية
     */
    public function extract(string $query): SearchEntitiesDTO
    {
        if (empty(trim($query))) {
            return SearchEntitiesDTO::empty();
        }

        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // ─── استخراج كل الـ entities ─────────────────────────────────
        $brand = $this->extractBrand($words);
        $model = $this->extractModel($normalized, $brand);
        $product = $this->buildProductPhrase($normalized, $brand, $model);
        $location = $this->extractLocation($words);
        [$minPrice, $maxPrice] = $this->extractPriceRange($normalized);
        $attributes = $this->extractAttributes($words);

        $hasEntities = $brand !== null
                    || $model !== null
                    || $location !== null
                    || $minPrice !== null
                    || ! empty($attributes);

        return new SearchEntitiesDTO(
            product: $product,
            brand: $brand,
            location: $location,
            model: $model,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            attributes: $attributes,
            hasEntities: $hasEntities,
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Brand Extraction
    // ─────────────────────────────────────────────────────────────────

    private function extractBrand(array $words): ?string
    {
        foreach ($words as $word) {
            if (in_array($word, self::KNOWN_BRANDS, true)) {
                return $word;
            }
        }

        // محاولة بالـ bigrams (كلمتين)
        for ($i = 0; $i < count($words) - 1; $i++) {
            $bigram = $words[$i].' '.$words[$i + 1];
            if (in_array($bigram, self::KNOWN_BRANDS, true)) {
                return $bigram;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Model Extraction
    // ─────────────────────────────────────────────────────────────────

    private function extractModel(string $text, ?string $brand): ?string
    {
        foreach (self::MODEL_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $candidate = trim($matches[1]);

                // تأكد أن الـ model مختلف عن الـ brand
                if ($candidate !== $brand) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Product Phrase Building
    // ─────────────────────────────────────────────────────────────────

    /**
     * بناء اسم المنتج الكامل من الـ brand + model
     *
     * مثال:
     *   brand="iphone", model="15 pro max" → "iphone 15 pro max"
     *   brand="samsung", model="s24 ultra" → "samsung s24 ultra"
     */
    private function buildProductPhrase(
        string $text,
        ?string $brand,
        ?string $model
    ): ?string {
        if ($brand === null && $model === null) {
            return null;
        }

        if ($brand !== null && $model !== null) {
            // تحقق إذا كانا متجاورين في النص
            $pattern = '/'.preg_quote($brand).'\s+'.preg_quote($model).'/i';
            if (preg_match($pattern, $text)) {
                return $brand.' '.$model;
            }
        }

        // أرجع ما توفر
        return trim(($brand ?? '').' '.($model ?? ''));
    }

    // ─────────────────────────────────────────────────────────────────
    // Location Extraction
    // ─────────────────────────────────────────────────────────────────

    private function extractLocation(array $words): ?string
    {
        // البحث المباشر في قائمة المواقع
        foreach ($words as $word) {
            if (in_array($word, self::KNOWN_LOCATIONS, true)) {
                return $word;
            }
        }

        // البحث بعد كلمة مؤشر (in, at, near)
        for ($i = 0; $i < count($words) - 1; $i++) {
            if (in_array($words[$i], self::LOCATION_INDICATORS, true)) {
                $candidate = $words[$i + 1];
                if (mb_strlen($candidate) >= 3) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Price Range Extraction
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array{0: ?float, 1: ?float} [minPrice, maxPrice]
     */
    private function extractPriceRange(string $text): array
    {
        $minPrice = null;
        $maxPrice = null;

        // pattern: "under 500" أو "less than 1000"
        if (preg_match('/(?:under|less than|below|أقل من|تحت)\s+(\d+)/i', $text, $matches)) {
            $maxPrice = (float) $matches[1];
        }

        // pattern: "over 200" أو "more than 500"
        if (preg_match('/(?:over|more than|above|أكثر من|فوق)\s+(\d+)/i', $text, $matches)) {
            $minPrice = (float) $matches[1];
        }

        // pattern: "200 to 500" أو "between 200 and 500"
        if (preg_match('/(\d+)\s+(?:to|-|and)\s+(\d+)/i', $text, $matches)) {
            $minPrice = (float) $matches[1];
            $maxPrice = (float) $matches[2];
        }

        // pattern: "$500" أو "500$"
        if (preg_match('/\$(\d+)/', $text, $matches)) {
            $maxPrice = $maxPrice ?? (float) $matches[1] * 1.2;
            $minPrice = $minPrice ?? (float) $matches[1] * 0.8;
        }

        return [$minPrice, $maxPrice];
    }

    // ─────────────────────────────────────────────────────────────────
    // Attributes Extraction
    // ─────────────────────────────────────────────────────────────────

    /**
     * استخراج صفات إضافية مثل اللون والحجم والمواصفات
     */
    private function extractAttributes(array $words): array
    {
        $attributes = [];

        $colorMap = [
            'red' => 'red', 'blue' => 'blue', 'black' => 'black',
            'white' => 'white', 'green' => 'green', 'gold' => 'gold',
            'silver' => 'silver', 'pink' => 'pink', 'purple' => 'purple',
            'أحمر' => 'red', 'أزرق' => 'blue', 'أسود' => 'black',
            'أبيض' => 'white', 'ذهبي' => 'gold', 'فضي' => 'silver',
        ];

        $storageMap = [
            '128gb' => '128GB', '256gb' => '256GB', '512gb' => '512GB',
            '1tb' => '1TB', '64gb' => '64GB',
        ];

        foreach ($words as $word) {
            if (isset($colorMap[$word])) {
                $attributes['color'] = $colorMap[$word];
            }
            if (isset($storageMap[$word])) {
                $attributes['storage'] = $storageMap[$word];
            }
        }

        return $attributes;
    }
}
