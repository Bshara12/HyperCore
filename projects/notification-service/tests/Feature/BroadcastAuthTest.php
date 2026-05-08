<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_authorizes_user_for_own_notification_channel(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-notifications.1.user.10',
            'socket_id' => '123.456',
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertStatus(200);
    }

    public function test_it_rejects_user_for_different_project(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-notifications.2.user.10',
            'socket_id' => '123.456',
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_it_rejects_non_user_channel_types(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-notifications.1.team.10',
            'socket_id' => '123.456',
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertStatus(403);
    }
}
