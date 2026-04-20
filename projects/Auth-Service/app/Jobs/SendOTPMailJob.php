<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOTPMailJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    protected User $user;
    protected string $otp;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::raw("Yout One Time Password is: {$this->otp}", function ($message) {
            $message->to($this->user->email)->subject('Email Verification OTP');
        });
    }
}
