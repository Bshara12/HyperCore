<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'channel' => $this->channel,
            'provider' => $this->provider,
            'status' => is_object($this->status) ? $this->status->value : $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'last_attempt_at' => optional($this->last_attempt_at)?->toISOString(),
            'next_retry_at' => optional($this->next_retry_at)?->toISOString(),
            'provider_message_id' => $this->provider_message_id,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'sent_at' => optional($this->sent_at)?->toISOString(),
            'delivered_at' => optional($this->delivered_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
