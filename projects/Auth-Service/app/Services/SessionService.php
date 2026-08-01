<?php

namespace App\Services;

use App\Repositories\SessionRepositoryInterface;

class SessionService
{
    protected $sessions;

    public function __construct(SessionRepositoryInterface $sessionRepository)
    {
        $this->sessions = $sessionRepository;
    }

    public function create(string $userId, ?string $ip, ?string $userAgent): string
    {
        $deviceName = $this->detectDevice($userAgent);

        return $this->sessions->createUserSession((int) $userId, $ip, $userAgent, $deviceName);
    }

    /**
     * منطق تحديد نوع الجهاز — قرار عمل (Business Logic) مكانه الصحيح هون بالـ Service
     * وليس بالـ Repository
     */
    private function detectDevice(?string $agent): string
    {
        if (!$agent) return 'Unknown device';

        $agent = strtolower($agent);

        if (str_contains($agent, 'windows')) return 'Windows device';
        if (str_contains($agent, 'iphone')) return 'iPhone';
        if (str_contains($agent, 'mac')) return 'Mac device';
        if (str_contains($agent, 'android')) return 'Android device';
        if (str_contains($agent, 'linux')) return 'Linux device';

        return 'Browser device';
    }

    public function createServiceSession(string $client_id, int $service_client_id): string
    {
        return $this->sessions->createServiceSession($client_id, $service_client_id);
    }
}
