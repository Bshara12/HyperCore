<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SessionService
{
    /*
    |--------------------------------------------------------------------------
    | Create session (device aware)
    |--------------------------------------------------------------------------
    */
    public function create(
        string $userId,
        ?string $ip,
        ?string $userAgent
    ): string {

        $sessionId = (string) Str::ulid();

        $deviceName = $this->detectDevice($userAgent);

        DB::table('my_sessions')->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_name' => $deviceName,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $sessionId;
    }

    /*
    |--------------------------------------------------------------------------
    | Simple device detection (phase 1)
    |--------------------------------------------------------------------------
    */
    private function detectDevice(?string $agent): string
    {
        if (!$agent) return 'Unknown device';

        $agent = strtolower($agent);

        if (str_contains($agent, 'windows')) return 'Windows device';
        if (str_contains($agent, 'mac')) return 'Mac device';
        if (str_contains($agent, 'iphone')) return 'iPhone';
        if (str_contains($agent, 'android')) return 'Android device';
        if (str_contains($agent, 'linux')) return 'Linux device';

        return 'Browser device';
    }
}
