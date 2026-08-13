<?php

use App\Observers\ProjectObserver;
use App\Models\Project;
use App\Services\MessageBroker\RabbitMQPublisher;

beforeEach(function () {
  $this->publisher = Mockery::mock(RabbitMQPublisher::class);
  $this->observer = new ProjectObserver($this->publisher);
});

afterEach(function () {
  Mockery::close();
});

test('it publishes event when project is created', function () {
  $project = Mockery::mock(Project::class)->makePartial();
  $project->owner_id = 15;
  $project->id = 101;
  $project->name = 'New E-commerce Project';
  $project->slug = 'new-ecommerce-project';

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.project.created', [
      'user_id'    => '15',
      'project_id' => 101,
      'name'       => 'New E-commerce Project',
      'slug'       => 'new-ecommerce-project',
    ]);

  $this->observer->created($project);
});
