<?php

namespace Tests;

use App\Services\Auth\AuthApiClient;
use App\Services\CMS\CMSApiClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AuthApiClient::class, function ($mock) {
            $mock->shouldReceive('getUserFromToken')
                ->andReturn([
                    'id' => 10,
                    'name' => 'Test User',
                    'roles' => [],
                    'permissions' => [
                        'notifications.create',
                        'notifications.read.any',
                        'notifications.manage',
                    ],
                ]);
        });

        $this->mock(CMSApiClient::class, function ($mock) {
            $mock->shouldReceive('resolveProject')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Project 1',
                ]);
        });
    }
}
