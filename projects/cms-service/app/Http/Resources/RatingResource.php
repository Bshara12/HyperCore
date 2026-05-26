<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Rating;


/**
 * @mixin Rating
 */
class RatingResource extends JsonResource
{
  public function toArray($request)
  {
    return [
      'id' => $this->id,
      'rating' => $this->rating,
      'review' => $this->review,

      // 'user' => $this->user ? [
      //     'id' => $this->user['id'],
      //     'name' => $this->user['name'],
      // ] : null,

      'user' => $this->whenLoaded('user', function () {

        $user = $this->resource->user;

        return [
          'id' => $user->id,
          'name' => $user->name,
        ];
      }),

      'created_at' => $this->created_at,
    ];
  }
}
