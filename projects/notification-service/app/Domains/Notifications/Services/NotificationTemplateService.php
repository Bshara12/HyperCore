<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Collection;

class NotificationTemplateService
{
    public function listForActor(NotificationActor $actor): Collection
    {
        return NotificationTemplate::query()
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId))
            ->orderBy('key')
            ->orderByDesc('version')
            ->get();
    }

    public function create(array $data): NotificationTemplate
    {
        return NotificationTemplate::create($data);
    }

    public function update(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $template->update($data);

        return $template->refresh();
    }

    public function activate(NotificationTemplate $template): NotificationTemplate
    {
        $template->forceFill(['is_active' => true])->save();

        return $template->refresh();
    }

    public function deactivate(NotificationTemplate $template): NotificationTemplate
    {
        $template->forceFill(['is_active' => false])->save();

        return $template->refresh();
    }

    public function findForActor(NotificationActor $actor, string $id): NotificationTemplate
    {
        return NotificationTemplate::query()
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId))
            ->findOrFail($id);
    }
}
