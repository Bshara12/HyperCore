<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class RatingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'review' => $this->review,

            'user' => $this->user ? [
                'id' => $this->user['id'],
                'name' => $this->user['name'],
            ] : null,

            'created_at' => $this->created_at,
        ];
    }
}