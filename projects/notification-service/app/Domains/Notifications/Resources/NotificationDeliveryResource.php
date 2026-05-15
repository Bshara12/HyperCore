<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Domains\Notifications\Models\NotificationDelivery;

/**
 * @property NotificationDelivery $resource
 */
class NotificationDeliveryResource extends JsonResource
{
  public function toArray(Request $request): array
  {

    /** @var NotificationDelivery $batch */
    $batch = $this->resource;
    return [
      'id' =>  $batch->id,
      'notification_id' =>  $batch->notification_id,
      'channel' =>  $batch->channel,
      'provider' =>  $batch->provider,
      // 'status' => is_object( $batch->status) ?  $batch->status->value :  $batch->status,
      'status' => $batch->status->value,
      'attempts' =>  $batch->attempts,
      'max_attempts' =>  $batch->max_attempts,
      'last_attempt_at' => optional($batch->last_attempt_at)?->toISOString(),
      'next_retry_at' => optional($batch->next_retry_at)?->toISOString(),
      'provider_message_id' =>  $batch->provider_message_id,
      'error_code' =>  $batch->error_code,
      'error_message' =>  $batch->error_message,
      'sent_at' => optional($batch->sent_at)?->toISOString(),
      'delivered_at' => optional($batch->delivered_at)?->toISOString(),
      'created_at' => optional($batch->created_at)?->toISOString(),
      'updated_at' => optional($batch->updated_at)?->toISOString(),
    ];
  }
}
