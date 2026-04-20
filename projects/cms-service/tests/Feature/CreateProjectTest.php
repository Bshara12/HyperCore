<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CreateProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_project()
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'Test Project',
            'owner_id' => $user->id,
            'supported_languages' => ['en', 'ar'],
            'enabled_modules' => ['cms'],
        ];

        $response = $this->postJson('/api/projects', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
        ]);

        // $this->assertDatabaseHas('project_user', [
        //     'user_id' => $user->id,
        //     'role' => 'owner',
        // ]);
    }
}
