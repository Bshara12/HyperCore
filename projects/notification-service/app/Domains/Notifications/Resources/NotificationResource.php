<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Domains\Notifications\Models\Notification;

/**
 * @property Notification $resource
 */
class NotificationResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    /** @var Notification $batch */
    $batch = $this->resource;

    return [
      'id' =>  $batch->id,
      'project_id' =>  $batch->project_id,
      'recipient_type' =>  $batch->recipient_type,
      'recipient_id' =>  $batch->recipient_id,

      'title' =>  $batch->title,
      'body' =>  $batch->body,
      'status' =>  $batch->status->value,
      'priority' =>  $batch->priority,

      'topic_key' =>  $batch->topic_key,
      'data' =>  $batch->data,
      'metadata' =>  $batch->metadata,

      'source' => [
        'type' =>  $batch->source_type,
        'service' =>  $batch->source_service,
        'id' =>  $batch->source_id,
      ],

      'read_at' => optional( $batch->read_at)?->toISOString(),
      'created_at' => optional( $batch->created_at)?->toISOString(),
      'updated_at' => optional( $batch->updated_at)?->toISOString(),
    ];
  }
}
