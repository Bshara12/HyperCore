<?php

namespace App\Services;

use App\Jobs\InvitationMailJob;
use App\Models\Invitation;
use App\Models\Project;
use App\Repositories\ProjectRepositoryInterface;

class ProjectServices {
    protected $projects;

    public function __construct(ProjectRepositoryInterface $projects)
    {
        $this->projects = $projects;
    }

    public function createProjectService(array $data): Project {
        $project = $this->projects->create($data);
        return $project;
    }

    public function creteInvitationService(array $data) : Invitation{
        $invitation = $this->projects->createInvitation($data);
        $this->generateInvitationOTP($invitation);
        return $invitation;
    }

    public function generateInvitationOTP(Invitation $invitation) {
        $otp = (string) rand(100000, 999999);
        $data = [
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'is_verified' => false
        ];
        $this->projects->updateInvitation($invitation,$data);

        InvitationMailJob::dispatch($invitation, $otp);
    }

    public function verifyOTP(Invitation $invitation, array $data):bool  {
        if(!$invitation->otp_code || $invitation->otp_code !== $data['otp']) return false;

        if($invitation->otp_expires_at && now()->greaterThan($invitation->otp_expires_at)) return false;

        $this->projects->updateInvitation($invitation, [
            'is_verified' => true,
            'otp_code' => null,
            'otp_expires_at' => null,
            'data' => $data['user_id']
        ]);

        return true;
    }
}
