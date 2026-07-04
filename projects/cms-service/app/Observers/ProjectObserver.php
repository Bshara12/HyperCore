<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\MessageBroker\RabbitMQPublisher;

class ProjectObserver
{
    public function __construct(
        private readonly RabbitMQPublisher $publisher
    ) {}

    /**
     * يُطلَق عند إنشاء مشروع جديد
     * المصدر: ProjectService::create() → Project::create()
     */
    public function created(Project $project): void
    {
        $this->publisher->publish('cms.project.created', [
            // owner_id هو صاحب المشروع
            'user_id'    => (string) $project->owner_id,
            'project_id' => $project->id,
            'name'       => $project->name,
            'slug'       => $project->slug,
        ]);
    }
}
