<?php

namespace App\Domains\Search\DTOs;

class SearchEntitiesDTO
{
    public function __construct(
        public readonly ?string $product,      // "iphone 15"
        public readonly ?string $brand,        // "apple"
        public readonly ?string $location,     // "romania"
        public readonly ?string $model,        // "15 pro max"
        public readonly ?float $minPrice,     // 500.0
        public readonly ?float $maxPrice,     // 1000.0
        public readonly array $attributes,   // ['color' => 'red', 'size' => '6.1']
        public readonly bool $hasEntities,  // هل وُجد شيء؟
    ) {}

    public static function empty(): self
    {
        return new self(
            product: null,
            brand: null,
            location: null,
            model: null,
            minPrice: null,
            maxPrice: null,
            attributes: [],
            hasEntities: false,
        );
    }

    public function toArray(): array
    {
        return [
            'product' => $this->product,
            'brand' => $this->brand,
            'location' => $this->location,
            'model' => $this->model,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'attributes' => $this->attributes,
            'has_entities' => $this->hasEntities,
        ];
    }
}
