<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'key' => $this->key,
            'channel' => $this->channel,
            'locale' => $this->locale,
            'version' => $this->version,
            'subject_template' => $this->subject_template,
            'body_template' => $this->body_template,
            'variables_schema' => $this->variables_schema,
            'defaults' => $this->defaults,
            'is_active' => $this->is_active,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
