<?php

namespace App\Jobs;

use App\Models\Invitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class InvitationMailJob implements ShouldQueue
{
    use Queueable ,Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    protected Invitation $invitation;
    protected string $otp;
    /**
     * Create a new job instance.
     */
    public function __construct(Invitation $invitation, string $otp)
    {
        $this->invitation = $invitation;
        $this->otp = $otp;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::raw("Yout One Time Password is: {$this->otp}", function ($message) {
            $message->to($this->invitation->email)->subject('Project Invitation Verification OTP');
        });
    }
}
