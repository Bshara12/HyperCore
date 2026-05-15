<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Domains\Notifications\Models\NotificationPreference;

/**
 * @property NotificationPreference $resource
 */
class NotificationPreferenceResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    /** @var NotificationPreference $batch */
    $batch = $this->resource;

    return [
      'id' =>  $batch->id,
      'project_id' =>  $batch->project_id,
      'recipient_type' =>  $batch->recipient_type,
      'recipient_id' =>  $batch->recipient_id,
      'topic_key' =>  $batch->topic_key,
      'channel' =>  $batch->channel,
      'enabled' =>  $batch->enabled,
      'mute_until' => optional( $batch->mute_until)?->toISOString(),
      'quiet_hours' =>  $batch->quiet_hours,
      'delivery_mode' =>  $batch->delivery_mode,
      'locale' =>  $batch->locale,
      'metadata' =>  $batch->metadata,
      'created_at' => optional( $batch->created_at)?->toISOString(),
      'updated_at' => optional( $batch->updated_at)?->toISOString(),
    ];
  }
}
