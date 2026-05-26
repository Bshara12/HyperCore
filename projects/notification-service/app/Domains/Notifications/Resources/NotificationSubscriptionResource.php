<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Domains\Notifications\Models\NotificationSubscription;

/**
 * @property NotificationSubscription $resource
 */
class NotificationSubscriptionResource extends JsonResource
{
  public function toArray(Request $request): array
  {
     /** @var NotificationSubscription $batch */
        $batch = $this->resource;

    return [
      'id' =>  $batch->id,
      'project_id' =>  $batch->project_id,
      'subscriber_type' =>  $batch->subscriber_type,
      'subscriber_id' =>  $batch->subscriber_id,
      'topic_key' =>  $batch->topic_key,
      'channel_mask' =>  $batch->channel_mask,
      'filters' =>  $batch->filters,
      'active' =>  $batch->active,
      'created_at' => optional( $batch->created_at)?->toISOString(),
      'updated_at' => optional( $batch->updated_at)?->toISOString(),
    ];
  }
}
